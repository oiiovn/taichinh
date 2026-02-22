<?php

namespace App\Services;

/**
 * Validation Layer: Đảm bảo state/mode không trái với dòng tiền thực.
 * Priority Hierarchy: 1. Liquidity survival  2. Cashflow direction (thu vs chi)  3. Debt pressure  4. Strategic state  5. Objective preference
 *
 * Ngưỡng thâm hụt: relative materiality = max(500k, 5% thu, 5% chi) — không hardcode 500k cho mọi mức thu.
 */
class FinancialConsistencyResolver
{
    private const DEFICIT_ABSOLUTE_MIN = 500_000;
    private const DEFICIT_PCT_INCOME = 0.05;
    private const DEFICIT_PCT_EXPENSE = 0.05;

    /**
     * Ngưỡng coi là có thâm hụt (materiality): max(500k, 5% thu, 5% chi).
     */
    private function deficitMaterialityThreshold(float $thu, float $outflow): float
    {
        $pctIncome = $thu > 0 ? $thu * self::DEFICIT_PCT_INCOME : 0.0;
        $pctExpense = $outflow > 0 ? $outflow * self::DEFICIT_PCT_EXPENSE : 0.0;
        return max(self::DEFICIT_ABSOLUTE_MIN, $pctIncome, $pctExpense);
    }

    /**
     * Resolve state và mode theo priority hierarchy; downgrade khi mâu thuẫn.
     *
     * @param  array{key: string, label: string, description: string}  $state  State từ classify()
     * @param  array{key: string, label: string, description: string}  $priorityMode  Mode từ classifyPriorityMode()
     * @param  array{debt_exposure?: float, liquid_balance?: float}  $position
     * @param  array{timeline?: array, sources?: array}  $projection
     * @return array{resolved_state: array, resolved_mode: array, downgraded: bool}
     */
    public function resolve(array $state, array $priorityMode, array $position, array $projection): array
    {
        $sources = $projection['sources'] ?? [];
        $canonical = $sources['canonical'] ?? [];
        $timeline = $projection['timeline'] ?? [];

        $thu = (float) ($sources['projected_income'] ?? $sources['recurring_income'] ?? 0);
        $chi = (float) ($sources['behavior_expense'] ?? 0) + (float) ($sources['recurring_expense'] ?? 0);
        $debtTotal = (float) ($sources['loan_schedule'] ?? 0);
        $months = max(1, count($timeline));
        $debtService = $debtTotal / $months;
        $outflow = $chi + $debtService;
        $monthlyDeficit = $outflow - $thu;
        $threshold = $this->deficitMaterialityThreshold($thu, $outflow);
        $hasDeficit = $monthlyDeficit > $threshold;

        $runwayFromLiq = $canonical['runway_from_liquidity_months'] ?? null;
        $incomeStability = (float) ($sources['income_stability_score'] ?? $canonical['income_stability_score'] ?? 1.0);
        $stateKey = $state['key'] ?? '';
        $modeKey = $priorityMode['key'] ?? '';
        $downgraded = false;
        $resolvedState = $state;
        $resolvedMode = $priorityMode;

        // Reliability override: stability < 0.4 → không cho optimization/growth
        if ($incomeStability < 0.4 && ($modeKey === 'optimization' || $modeKey === 'growth')) {
            $resolvedMode = [
                'key' => 'defensive',
                'label' => 'Chế độ phòng thủ',
                'description' => 'Thu nhập biến động mạnh — ưu tiên dự phòng tối thiểu 6 tháng chi trước khi tối ưu dài hạn.',
            ];
            $downgraded = true;
        }

        // Tầng 2: Cashflow direction — chi > thu thì không được accumulation / stable_conservative
        if ($hasDeficit && in_array($stateKey, ['accumulation_phase', 'stable_conservative'], true)) {
            $resolvedState = $this->downgradeToFragileLiquidity($runwayFromLiq);
            $downgraded = true;
            if ($modeKey === 'growth' || $modeKey === 'optimization') {
                $resolvedMode = [
                    'key' => 'defensive',
                    'label' => 'Chế độ phòng thủ',
                    'description' => 'Dòng tiền đang thâm hụt — ưu tiên bảo vệ buffer và điều chỉnh thu chi.',
                ];
            }
        }

        // Tầng 1: Liquidity survival — runway sắp hết thì mode phải crisis/defensive
        if ($runwayFromLiq !== null && $runwayFromLiq <= 1 && $monthlyDeficit > $threshold) {
            if ($resolvedMode['key'] === 'optimization' || $resolvedMode['key'] === 'growth') {
                $resolvedMode = [
                    'key' => 'crisis',
                    'label' => 'Chế độ khủng hoảng',
                    'description' => 'Thanh khoản chỉ đủ tối đa 1 tháng — cần hành động khẩn cấp.',
                ];
                $downgraded = true;
            }
        }

        return [
            'resolved_state' => $resolvedState,
            'resolved_mode' => $resolvedMode,
            'downgraded' => $downgraded,
        ];
    }

    private function downgradeToFragileLiquidity(?int $runwayMonths): array
    {
        $run = $runwayMonths !== null && $runwayMonths >= 2 ? $runwayMonths : '';
        $desc = $run !== ''
            ? "Chi vượt thu (thâm hụt) — trạng thái điều chỉnh thành thanh khoản cần theo dõi. Runway khoảng {$run} tháng."
            : 'Chi vượt thu (thâm hụt) — trạng thái điều chỉnh thành thanh khoản cần theo dõi.';
        return [
            'key' => 'fragile_liquidity',
            'label' => 'Thanh khoản cần theo dõi',
            'description' => $desc,
        ];
    }
}
