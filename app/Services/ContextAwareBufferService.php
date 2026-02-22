<?php

namespace App\Services;

/**
 * Buffer (runway khuyến nghị) động theo ngữ cảnh:
 * - Income volatility: thu dao động → cần buffer dài hơn.
 * - Debt pressure: nợ/trả nợ cao → cần buffer an toàn hơn.
 * - Fixed cost ratio: chi cố định/tổng chi cao → khó cắt giảm nhanh, cần buffer.
 * - Economic: income_concentration > 0.7 → +1–2 tháng; platform_dependency high → +1 tháng.
 *
 * Brain cấp 5 – thay thế/bổ sung required_runway_months cố định (3/6).
 */
class ContextAwareBufferService
{
    private const BASE_MONTHS = 3;

    private const MIN_MONTHS = 2;

    private const MAX_MONTHS = 12;

    /**
     * Tính số tháng buffer khuyến nghị theo ngữ cảnh.
     *
     * @param  array{volatility_ratio?: float, income_stability_score?: float, operating_income?: float, monthly_expense?: float, debt_service?: float, debt_exposure?: float, recurring_income?: float}  $canonical  Đã có từ projection (sources.canonical)
     * @param  array{debt_exposure?: float, receivable_exposure?: float, liquid_balance?: float}  $position
     * @param  array{income_concentration?: float, platform_dependency?: string}|null  $economicContext  Từ EconomicContextService → rủi ro tập trung = buffer cao hơn
     * @param  bool  $forecastErrorHigh  forecast_error cao → cộng 1 tháng (conservative bias)
     * @return array{recommended_runway_months: int, components: array{base: int, volatility_add: float, debt_pressure_add: float, fixed_cost_add: float, concentration_add?: float, platform_add?: float, forecast_error_add?: float}}
     */
    public function recommend(array $canonical, array $position, ?array $economicContext = null, bool $forecastErrorHigh = false): array
    {
        $income = (float) ($canonical['operating_income'] ?? $canonical['effective_income'] ?? 0) ?: 1.0;
        $expense = (float) ($canonical['monthly_expense'] ?? $canonical['operating_expense'] ?? 0);
        $debtService = (float) ($canonical['debt_service'] ?? 0);
        $debtExposure = (float) ($position['debt_exposure'] ?? $canonical['debt_exposure'] ?? 0);
        $volatilityRatio = (float) ($canonical['volatility_ratio'] ?? 0);
        $stabilityScore = (float) ($canonical['income_stability_score'] ?? 1.0);

        $volatilityAdd = $this->volatilityComponent($volatilityRatio, $stabilityScore);
        $debtPressureAdd = $this->debtPressureComponent($income, $debtService, $debtExposure);
        $fixedCostAdd = $this->fixedCostComponent($income, $expense, $debtService);
        $concentrationAdd = $this->concentrationComponent($economicContext);
        $platformAdd = $this->platformComponent($economicContext);
        $forecastErrorAdd = $forecastErrorHigh ? 1.0 : 0.0;

        $total = self::BASE_MONTHS + $volatilityAdd + $debtPressureAdd + $fixedCostAdd + $concentrationAdd + $platformAdd + $forecastErrorAdd;
        $recommended = (int) round(min(self::MAX_MONTHS, max(self::MIN_MONTHS, $total)));

        $components = [
            'base' => self::BASE_MONTHS,
            'volatility_add' => round($volatilityAdd, 1),
            'debt_pressure_add' => round($debtPressureAdd, 1),
            'fixed_cost_add' => round($fixedCostAdd, 1),
        ];
        if ($concentrationAdd > 0) {
            $components['concentration_add'] = round($concentrationAdd, 1);
        }
        if ($platformAdd > 0) {
            $components['platform_add'] = round($platformAdd, 1);
        }
        if ($forecastErrorAdd > 0) {
            $components['forecast_error_add'] = round($forecastErrorAdd, 1);
        }

        return [
            'recommended_runway_months' => $recommended,
            'components' => $components,
        ];
    }

    /** Thu tập trung 1 nguồn (income_concentration > 0.7) → +1–2 tháng. */
    private function concentrationComponent(?array $economicContext): float
    {
        if ($economicContext === null) {
            return 0.0;
        }
        $c = (float) ($economicContext['income_concentration'] ?? 0);
        if ($c >= 0.85) {
            return 2.0;
        }
        if ($c >= 0.7) {
            return 1.5;
        }
        return 0.0;
    }

    /** Phụ thuộc ít nền tảng (platform_dependency high) → +1 tháng. */
    private function platformComponent(?array $economicContext): float
    {
        if ($economicContext === null) {
            return 0.0;
        }
        $dep = (string) ($economicContext['platform_dependency'] ?? '');
        return strtolower($dep) === 'high' ? 1.0 : 0.0;
    }

    /** Thu dao động cao → cộng thêm 0–3 tháng. */
    private function volatilityComponent(float $volatilityRatio, float $stabilityScore): float
    {
        if ($stabilityScore >= 0.8) {
            return 0;
        }
        if ($stabilityScore <= 0.3) {
            return 3.0;
        }
        if ($volatilityRatio >= 0.3) {
            return 2.0;
        }
        if ($volatilityRatio >= 0.15) {
            return 1.0;
        }
        return 0.5;
    }

    /** Áp lực nợ (debt service / thu, hoặc DTI ảo) cao → cộng 0–3 tháng. */
    private function debtPressureComponent(float $income, float $debtService, float $debtExposure): float
    {
        if ($income <= 0) {
            return 2.0;
        }
        $serviceRatio = $debtService / $income;
        $yearlyIncome = $income * 12;
        $dti = $yearlyIncome > 0 ? $debtExposure / $yearlyIncome : 0;
        if ($serviceRatio >= 0.4 || $dti >= 1.0) {
            return 3.0;
        }
        if ($serviceRatio >= 0.25 || $dti >= 0.6) {
            return 2.0;
        }
        if ($serviceRatio >= 0.15 || $dti >= 0.35) {
            return 1.0;
        }
        return 0;
    }

    /** Chi cố định (expense + debt service) chiếm tỷ lệ cao so với thu → khó cắt giảm, cộng 0–2 tháng. */
    private function fixedCostComponent(float $income, float $expense, float $debtService): float
    {
        if ($income <= 0) {
            return 1.0;
        }
        $fixedRatio = ($expense + $debtService) / $income;
        if ($fixedRatio >= 0.95) {
            return 2.0;
        }
        if ($fixedRatio >= 0.85) {
            return 1.0;
        }
        if ($fixedRatio >= 0.75) {
            return 0.5;
        }
        return 0;
    }
}
