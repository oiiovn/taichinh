<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Debt Stress Index (0–100): thay/bổ sung risk_label đơn giản.
 * DSI > 70 → cảnh báo cấu trúc. Phục vụ hướng A (người nợ nhiều).
 */
class DebtStressService
{
    /** Ngưỡng DSI từ đó coi là stress cấu trúc. */
    public const STRUCTURAL_WARNING_THRESHOLD = 70;

    /** Khi volatility_cluster high → cảnh báo sớm hơn. */
    private const STRUCTURAL_WARNING_THRESHOLD_HIGH_VOLATILITY = 65;

    /**
     * Tính chỉ số stress nợ 0–100.
     *
     * @param  array{debt_exposure: float, receivable_exposure: float, net_leverage: float, liquid_balance?: float}  $position
     * @param  array{timeline?: array, sources?: array}|null  $projection
     * @param  array{survival_horizon_months?: int|null}|null  $optimization
     * @param  \Illuminate\Support\Collection<int, object{outstanding: float, due_date: mixed, is_receivable: bool}>  $oweItems
     * @param  array{volatility_cluster?: string}|null  $economicContext  volatility_cluster high → ngưỡng cảnh báo 65
     * @return array{index: int, level: string, structural_warning: bool, components: array}
     */
    public function computeDebtStressIndex(
        array $position,
        ?array $projection,
        ?array $optimization,
        Collection $oweItems,
        ?array $economicContext = null
    ): array {
        $sources = $projection['sources'] ?? [];
        $canonical = $sources['canonical'] ?? [];
        $incomeAvg = (float) ($sources['projected_income'] ?? $sources['recurring_income'] ?? 0);
        $expenseAvg = (float) ($sources['behavior_expense'] ?? 0) + (float) ($sources['recurring_expense'] ?? 0);
        $debtExposure = (float) ($position['debt_exposure'] ?? 0);
        $receivableExposure = (float) ($position['receivable_exposure'] ?? 0);
        $liquidBalance = (float) ($position['liquid_balance'] ?? 0);
        $freeCashflowAfterDebt = isset($canonical['free_cashflow_after_debt']) ? (float) $canonical['free_cashflow_after_debt'] : null;
        $debtService = (float) ($canonical['debt_service'] ?? 0);
        $runwayFromLiquidity = $canonical['runway_from_liquidity_months'] ?? null;
        $volatilityRatio = (float) ($sources['volatility_ratio'] ?? 0);

        $incomeYearly = $incomeAvg > 0 ? $incomeAvg * 12 : 0;
        $debtToIncome = $incomeYearly > 0 ? $debtExposure / $incomeYearly : ($debtExposure > 0 ? 1.0 : 0.0);
        $debtServicePct = $incomeAvg > 0 ? ($debtService / $incomeAvg) : 0.0;
        $cashflowCoverage = $incomeAvg > 0 && $expenseAvg > 0 ? $incomeAvg / $expenseAvg : 0.0;

        $activeOwe = $oweItems->where('is_receivable', false)->filter(fn ($i) => ($i->is_active ?? true));
        $totalOwe = $activeOwe->sum('outstanding') ?: 1.0;
        $today = Carbon::today();
        $shortTermOutstanding = 0.0;
        foreach ($activeOwe as $item) {
            $d = $item->due_date ?? ($item->entity->due_date ?? null);
            if ($d !== null) {
                $due = $d instanceof Carbon ? $d : Carbon::parse($d);
                if ($today->diffInMonths($due, false) <= 12) {
                    $shortTermOutstanding += (float) ($item->outstanding ?? 0);
                }
            } else {
                $shortTermOutstanding += (float) ($item->outstanding ?? 0);
            }
        }
        $shortTermRatio = $totalOwe > 0 ? ($shortTermOutstanding / $totalOwe) : 0.0;

        $largestPrincipal = $activeOwe->isEmpty() ? 0 : $activeOwe->max('outstanding');
        $concentrationRisk = $debtExposure > 0 && $largestPrincipal > 0
            ? ($largestPrincipal / $debtExposure) : 0.0;

        $dtiScore = $this->scoreDti($debtToIncome);
        $debtServiceScore = $this->scoreDebtServicePct($debtServicePct);
        $shortTermScore = $this->scoreShortTermRatio($shortTermRatio);
        $concentrationScore = $this->scoreConcentration($concentrationRisk);
        $runwayScore = $this->scoreRunway($runwayFromLiquidity);
        $volatilityScore = $this->scoreVolatility($volatilityRatio);
        $freeCashflowScore = $this->scoreFreeCashflow($freeCashflowAfterDebt, $incomeAvg);

        $weights = [
            'dti' => 0.25,
            'debt_service_pct' => 0.20,
            'short_term_ratio' => 0.15,
            'concentration' => 0.15,
            'runway' => 0.15,
            'volatility' => 0.05,
            'free_cashflow' => 0.05,
        ];
        $raw = $dtiScore * $weights['dti']
            + $debtServiceScore * $weights['debt_service_pct']
            + $shortTermScore * $weights['short_term_ratio']
            + $concentrationScore * $weights['concentration']
            + $runwayScore * $weights['runway']
            + $volatilityScore * $weights['volatility']
            + $freeCashflowScore * $weights['free_cashflow'];
        $index = (int) round(min(100, max(0, $raw * 100)));
        $level = $index >= 70 ? 'high' : ($index >= 40 ? 'medium' : 'low');
        $threshold = ($economicContext !== null && strtolower((string) ($economicContext['volatility_cluster'] ?? '')) === 'high')
            ? self::STRUCTURAL_WARNING_THRESHOLD_HIGH_VOLATILITY
            : self::STRUCTURAL_WARNING_THRESHOLD;
        $structuralWarning = $index >= $threshold;

        return [
            'index' => $index,
            'level' => $level,
            'structural_warning' => $structuralWarning,
            'components' => [
                'debt_to_income_ratio' => round($debtToIncome, 2),
                'debt_service_pct_of_income' => round($debtServicePct * 100, 1),
                'short_term_debt_ratio' => round($shortTermRatio, 2),
                'concentration_risk' => round($concentrationRisk, 2),
                'runway_from_liquidity_months' => $runwayFromLiquidity,
                'volatility_ratio' => round($volatilityRatio, 4),
                'free_cashflow_after_debt' => $freeCashflowAfterDebt !== null ? (int) round($freeCashflowAfterDebt) : null,
            ],
        ];
    }

    /** DTI: 0 = no stress, 1 = max stress. */
    private function scoreDti(float $dti): float
    {
        if ($dti <= 0) {
            return 0.0;
        }
        if ($dti >= 1.0) {
            return 1.0;
        }
        if ($dti <= 0.3) {
            return $dti / 0.3 * 0.3;
        }
        if ($dti <= 0.6) {
            return 0.3 + ($dti - 0.3) / 0.3 * 0.4;
        }
        return 0.7 + ($dti - 0.6) / 0.4 * 0.3;
    }

    /** % thu nhập dùng trả nợ. */
    private function scoreDebtServicePct(float $pct): float
    {
        if ($pct <= 0) {
            return 0.0;
        }
        if ($pct >= 0.5) {
            return 1.0;
        }
        return min(1.0, $pct * 2.0);
    }

    private function scoreShortTermRatio(float $ratio): float
    {
        return min(1.0, $ratio * 1.2);
    }

    private function scoreConcentration(float $c): float
    {
        if ($c >= 0.8) {
            return 1.0;
        }
        return min(1.0, $c / 0.5);
    }

    private function scoreRunway(?float $months): float
    {
        if ($months === null || $months >= 6) {
            return 0.0;
        }
        if ($months <= 0) {
            return 1.0;
        }
        return 1.0 - $months / 6.0;
    }

    private function scoreVolatility(float $v): float
    {
        if ($v >= 0.4) {
            return 1.0;
        }
        return min(1.0, $v / 0.2);
    }

    private function scoreFreeCashflow(?float $fcad, float $incomeAvg): float
    {
        if ($fcad === null || $incomeAvg <= 0) {
            return 0.5;
        }
        if ($fcad < 0) {
            return 1.0;
        }
        $pct = $fcad / $incomeAvg;
        if ($pct >= 0.2) {
            return 0.0;
        }
        return 1.0 - $pct / 0.2;
    }
}
