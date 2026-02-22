<?php

namespace App\Services;

/**
 * Tầng 6 – Decision Core: gom structural, behavioral, economic, drift, objective
 * thành decision_bundle (levers) và gọi BrainModeService → brain_mode (khung narrative).
 */
class DecisionCoreService
{
    public function __construct(
        private BrainModeService $brainModeService
    ) {}

    /**
     * Trả decision_bundle + brain_mode để pipeline inject vào cognitive_input.
     *
     * @param  array{key?: string}|null  $financialState
     * @param  array{key?: string}|null  $priorityMode
     * @param  array{tone?: string}|null  $contextualFrame
     * @param  array<string, mixed>  $canonical  projection sources canonical
     * @param  array{min_expense_reduction_pct?: float|null, summary?: string}|null  $optimization
     * @param  array{income_concentration?: float, platform_dependency?: string, volatility_cluster?: string}  $economicContext
     * @param  array{summary?: string, repeated_high_dsi?: bool, feedback_count_increase?: int}  $driftSignals
     * @param  array{execution_consistency_score_reduce_expense?: float|null, execution_consistency_score?: float|null}  $behavioralScores
     * @param  array{key?: string}|null  $objective
     * @param  array{conservative_bias?: float}|null  $userParams  user_brain_params (Learning Loop)
     * @param  array{active_count: int, thresholds: array, aggregate: array}|null  $thresholdSummary  Ngưỡng ngân sách (deviation_pct, breach_streak, self_control_index)
     * @param  array{discipline_score?: float|null, planning_realism_index?: float|null, impulse_risk_score?: float|null, behavior_mismatch_warning?: bool}|null  $budgetIntelligence  Hệ đo kỷ luật tài chính
     */
    public function compute(
        ?array $financialState,
        ?array $priorityMode,
        ?array $contextualFrame,
        array $canonical,
        ?array $optimization,
        array $economicContext = [],
        array $driftSignals = [],
        array $behavioralScores = [],
        ?array $objective = null,
        ?array $userParams = null,
        ?array $thresholdSummary = null,
        ?array $budgetIntelligence = null
    ): array {
        $brainMode = $this->brainModeService->resolve(
            $financialState,
            $priorityMode,
            $contextualFrame,
            $economicContext,
            $driftSignals,
            $behavioralScores,
            $userParams,
            $thresholdSummary,
            $budgetIntelligence
        );

        $baseRunway = (int) ($canonical['required_runway_months'] ?? 3);
        $runwayWeight = (float) ($brainMode['runway_weight'] ?? 1.0);
        $requiredRunwayMonths = max(1, (int) round($baseRunway * $runwayWeight));

        $minExpensePct = isset($optimization['min_expense_reduction_pct']) ? (float) $optimization['min_expense_reduction_pct'] : null;
        $expenseReductionCapPct = $minExpensePct !== null ? min(35, max(5, (int) round($minExpensePct) + 10)) : 25;
        $expenseReductionCapPct += (int) ($brainMode['expense_cap_modifier'] ?? 0);
        $expenseReductionCapPct = max(5, min(40, $expenseReductionCapPct));
        if ($userParams !== null && ! empty($userParams['expense_suggestion_soften'])) {
            $expenseReductionCapPct = max(5, (int) round($expenseReductionCapPct * 0.8));
        }

        $aggression = (float) ($brainMode['aggression'] ?? 0.5);
        $debtUrgencyBoost = round(1.0 + 0.25 * $aggression, 2);

        $crisisThresholdDsi = 75;
        if (strtolower((string) ($economicContext['volatility_cluster'] ?? '')) === 'high') {
            $crisisThresholdDsi = 65;
        }
        $crisisThresholdDsi += (int) ($brainMode['crisis_threshold_dsi_delta'] ?? 0);
        $crisisThresholdDsi = max(50, min(85, $crisisThresholdDsi));

        $surplusRetentionPct = strtolower((string) ($economicContext['platform_dependency'] ?? '')) === 'high' ? 40 : 25;
        if ($userParams !== null && isset($userParams['conservative_bias']) && (float) $userParams['conservative_bias'] > 0) {
            $surplusRetentionPct = min(50, $surplusRetentionPct + (int) round((float) $userParams['conservative_bias'] * 10));
        }

        $decisionBundle = [
            'required_runway_months' => $requiredRunwayMonths,
            'expense_reduction_cap_pct' => $expenseReductionCapPct,
            'debt_urgency_boost' => $debtUrgencyBoost,
            'crisis_threshold_dsi' => $crisisThresholdDsi,
            'surplus_retention_pct' => $surplusRetentionPct,
        ];

        return [
            'decision_bundle' => $decisionBundle,
            'brain_mode' => $brainMode,
        ];
    }
}
