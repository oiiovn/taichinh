<?php

namespace App\Services;

/**
 * Capital Stability Model — 4 trụ, mỗi trụ 0–1.
 * Chuẩn hóa theo cấu trúc (ratio, runway), không hardcode số tuyệt đối.
 */
class CapitalStabilityService
{
    /**
     * Tính 4 trụ Capital Stability từ canonical + optional monthly series.
     *
     * @param  array{effective_income: float, monthly_expense: float, monthly_debt: float, operating_margin?: float, income_stability_score?: float, runway_from_liquidity_months?: int|null, available_liquidity?: float, debt_service?: float, dscr?: float|null}  $canonical
     * @param  array<float>|null  $surplusSeries  Chuỗi surplus theo tháng (để percentile nội bộ user)
     * @return array{pillars: array{cashflow_integrity: float, liquidity_depth: float, debt_load_quality: float, structural_flexibility: float}, components: array}
     */
    public function score(array $canonical, ?array $surplusSeries = null): array
    {
        $income = max(0.01, (float) ($canonical['effective_income'] ?? $canonical['operating_income'] ?? 0));
        $expense = (float) ($canonical['monthly_expense'] ?? 0);
        $debtService = (float) ($canonical['monthly_debt'] ?? $canonical['debt_service'] ?? 0);
        $outflow = $expense + $debtService;
        $surplus = $income - $outflow;
        $surplusRatio = $income > 0 ? $surplus / $income : 0.0;
        $incomeStability = (float) ($canonical['income_stability_score'] ?? 1.0);
        $runwayMonths = $canonical['runway_from_liquidity_months'] ?? null;
        $availableLiquidity = (float) ($canonical['available_liquidity'] ?? 0);
        $recurringIncome = (float) ($canonical['recurring_income'] ?? $income);
        $recurringCoverage = $income > 0 ? min(1.0, $recurringIncome / $income) : 0.0;
        $dscr = isset($canonical['dscr']) && $canonical['dscr'] !== null ? (float) $canonical['dscr'] : null;
        $debtExposure = (float) ($canonical['debt_exposure'] ?? 0);
        $volatilityRatio = (float) ($canonical['volatility_ratio'] ?? (1 - $incomeStability));

        $cfg = config('capital_hierarchy.normalization', []);

        $cashflowIntegrity = $this->pillarCashflowIntegrity($surplusRatio, $incomeStability, $recurringCoverage, $cfg, $surplusSeries);
        $liquidityDepth = $this->pillarLiquidityDepth($runwayMonths, $availableLiquidity, $expense, $cfg);
        $debtLoadQuality = $this->pillarDebtLoadQuality($income, $debtService, $debtExposure, $dscr, $cfg);
        $structuralFlexibility = $this->pillarStructuralFlexibility($incomeStability, $volatilityRatio, $income, $debtService, $expense, $cfg);

        return [
            'pillars' => [
                'cashflow_integrity' => round($cashflowIntegrity, 4),
                'liquidity_depth' => round($liquidityDepth, 4),
                'debt_load_quality' => round($debtLoadQuality, 4),
                'structural_flexibility' => round($structuralFlexibility, 4),
            ],
            'components' => [
                'surplus_ratio' => $surplusRatio,
                'income_consistency' => $incomeStability,
                'recurring_coverage' => $recurringCoverage,
                'runway_months' => $runwayMonths,
                'liquidity_expense_ratio' => $expense > 0 ? $availableLiquidity / $expense : 0,
                'debt_service_ratio' => $income > 0 ? $debtService / $income : 0,
                'volatility_exposure' => $volatilityRatio,
            ],
        ];
    }

    private function pillarCashflowIntegrity(float $surplusRatio, float $incomeConsistency, float $recurringCoverage, array $cfg, ?array $surplusSeries): float
    {
        $range = $cfg['surplus_ratio_range'] ?? [-0.3, 0.4];
        $low = $range[0];
        $high = $range[1];
        $surplusScore = $high > $low ? min(1.0, max(0.0, ($surplusRatio - $low) / ($high - $low))) : 0.5;
        $consistencyScore = $incomeConsistency;
        $recurringScore = $recurringCoverage;
        $blend = ($surplusScore * 0.5) + ($consistencyScore * 0.3) + ($recurringScore * 0.2);

        return (float) min(1.0, max(0.0, $blend));
    }

    private function pillarLiquidityDepth(?int $runwayMonths, float $availableLiquidity, float $monthlyExpense, array $cfg): float
    {
        $cap = (int) ($cfg['runway_cap_months'] ?? 12);
        $runwayScore = 0.0;
        if ($runwayMonths !== null) {
            $runwayScore = $cap > 0 ? min(1.0, (float) $runwayMonths / $cap) : ($runwayMonths >= 6 ? 1.0 : 0.5);
        }
        $minRatio = (float) ($cfg['liquidity_expense_min_ratio'] ?? 0);
        $goodRatio = (float) ($cfg['liquidity_expense_good_ratio'] ?? 6);
        $ratio = $monthlyExpense > 0 ? $availableLiquidity / $monthlyExpense : 0;
        $ratioScore = $goodRatio > $minRatio ? min(1.0, max(0.0, ($ratio - $minRatio) / ($goodRatio - $minRatio))) : 0.5;
        if ($runwayMonths === null && $ratio <= 0) {
            return 0.0;
        }

        return (float) min(1.0, max(0.0, ($runwayScore * 0.6) + ($ratioScore * 0.4)));
    }

    private function pillarDebtLoadQuality(float $income, float $debtService, float $debtExposure, ?float $dscr, array $cfg): float
    {
        if ($income <= 0 && $debtExposure <= 0) {
            return 1.0;
        }
        if ($debtExposure <= 0 && $debtService <= 0) {
            return 1.0;
        }
        $dtiSafe = (float) ($cfg['dti_safe_max'] ?? 0.35);
        $dtiStress = (float) ($cfg['dti_stress'] ?? 0.6);
        $dsr = $income > 0 ? $debtService / $income : 1.0;
        $dsrScore = $dsr <= $dtiSafe ? 1.0 : ($dsr >= $dtiStress ? 0.0 : 1.0 - (($dsr - $dtiSafe) / ($dtiStress - $dtiSafe)));
        $dscrScore = 0.5;
        if ($dscr !== null) {
            if ($dscr >= 1.5) {
                $dscrScore = 1.0;
            } elseif ($dscr >= 1.0) {
                $dscrScore = 0.7;
            } elseif ($dscr >= 0.5) {
                $dscrScore = 0.3;
            } else {
                $dscrScore = 0.0;
            }
        }
        $blend = ($dsrScore * 0.6) + ($dscrScore * 0.4);

        return (float) min(1.0, max(0.0, $blend));
    }

    private function pillarStructuralFlexibility(float $incomeStability, float $volatilityExposure, float $income, float $debtService, float $expense, array $cfg): float
    {
        $volatilityScore = 1.0 - min(1.0, $volatilityExposure);
        $fixedCostRatio = ($income > 0 && ($expense + $debtService) > 0) ? ($expense + $debtService) / $income : 0;
        $fixedScore = $fixedCostRatio <= 0.5 ? 1.0 : ($fixedCostRatio >= 1.0 ? 0.0 : 1.0 - (($fixedCostRatio - 0.5) / 0.5));
        $financingDependency = $income > 0 ? $debtService / $income : 0;
        $financingScore = $financingDependency <= 0.1 ? 1.0 : ($financingDependency >= 0.5 ? 0.0 : 1.0 - (($financingDependency - 0.1) / 0.4));

        return (float) min(1.0, max(0.0, ($volatilityScore * 0.4) + ($fixedScore * 0.3) + ($financingScore * 0.3)));
    }
}
