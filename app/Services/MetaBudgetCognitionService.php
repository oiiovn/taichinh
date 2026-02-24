<?php

namespace App\Services;

/**
 * Meta Budget Cognition Layer — đánh giá siêu thông minh từ ngưỡng ngân sách.
 * Không chỉ "có vượt ngưỡng không" mà: kiểm soát vì kỷ luật thật hay vì chưa tới thời điểm chi,
 * xu hướng drift, risk ẩn, executive summary.
 *
 * Input: threshold_summary + budget_intelligence (từ BudgetThresholdService + BudgetIntelligenceService).
 * Output: Overall Budget Cognition Object (cognitive_state, budget_health_score, behavioral_pattern, ...).
 */
class MetaBudgetCognitionService
{
    /** Cognitive states */
    public const STATE_CONTROLLED_GROWTH = 'controlled_growth';

    public const STATE_DRIFTING_STABILITY = 'drifting_stability';

    public const STATE_REACTIVE_CONTROL = 'reactive_control';

    public const STATE_STRUCTURAL_STRESS = 'structural_stress';

    public const STATE_INSUFFICIENT_DATA = 'insufficient_data';

    /** Behavioral patterns (Layer 2) */
    public const PATTERN_DISCIPLINED_STRUCTURAL = 'disciplined_structural';

    public const PATTERN_REACTIVE_CONTROLLER = 'reactive_controller';

    public const PATTERN_VOLATILE_REGULATOR = 'volatile_regulator';

    public const PATTERN_ILLUSORY_DISCIPLINE = 'illusory_discipline';

    /** Psychological profiles (Layer 3) */
    public const PROFILE_OVER_TIGHT = 'over_tight_planning';

    public const PROFILE_IMPULSE_PRONE = 'impulse_prone';

    public const PROFILE_SELF_JUSTIFYING = 'self_justifying_drift';

    public const PROFILE_REALISTIC_PLANNER = 'realistic_planner';

    /** Drift profiles (Layer 4) */
    public const DRIFT_STABLE = 'stable';

    public const DRIFT_GRADUAL = 'gradual_drift';

    public const DRIFT_ACCELERATING = 'accelerating_drift';

    public const DRIFT_STRUCTURAL_CREEP = 'structural_creep';

    /** Dominant force */
    public const FORCE_INTERNAL_CONTROL = 'internal_control';

    public const FORCE_EXTERNAL_CONSTRAINT = 'external_constraint';

    public const FORCE_TIMING_EFFECT = 'timing_effect';

    public const FORCE_UNCLEAR = 'unclear';

    public function __construct() {}

    /**
     * @param  array{active_count: int, thresholds: array, aggregate: array}  $thresholdSummary
     * @param  array<string, mixed>  $budgetIntelligence
     * @param  array{debt_stress_index?: int|null, surplus_positive?: bool|null}|null  $context
     * @return array{cognitive_state: string, budget_health_score: int|null, behavioral_pattern: string|null, psychological_profile: string|null, drift_profile: string|null, dominant_force: string, hidden_risk_flag: bool, trajectory_vector: string, meta_warning: string|null, executive_summary: string}
     */
    public function compute(array $thresholdSummary, array $budgetIntelligence, ?array $context = []): array
    {
        $activeCount = (int) ($thresholdSummary['active_count'] ?? 0);
        if ($activeCount === 0) {
            return $this->emptyCognition();
        }

        $agg = $thresholdSummary['aggregate'] ?? [];
        $thresholds = $thresholdSummary['thresholds'] ?? [];

        $behavioralPattern = $this->resolveBehavioralConsistency($budgetIntelligence, $agg);
        $psychologicalProfile = $this->resolvePsychologicalBias($budgetIntelligence);
        $driftProfile = $this->resolveDriftDetection($budgetIntelligence, $thresholds);
        $alignmentOk = $this->resolveContextAlignment($budgetIntelligence, $context);
        $predictive = $this->resolvePredictive($budgetIntelligence);

        $cognitiveState = $this->resolveCognitiveState(
            $budgetIntelligence,
            $agg,
            $behavioralPattern,
            $psychologicalProfile,
            $driftProfile,
            $alignmentOk
        );
        $budgetHealthScore = $this->computeBudgetHealthScore($budgetIntelligence, $agg, $cognitiveState);
        $dominantForce = $this->resolveDominantForce($budgetIntelligence, $agg, $thresholds, $behavioralPattern);
        $hiddenRiskFlag = $this->detectHiddenRisk($budgetIntelligence, $thresholds);
        $trajectoryVector = $this->resolveTrajectory($budgetIntelligence);

        [$executiveSummary, $metaWarning] = $this->buildNarrative(
            $cognitiveState,
            $budgetIntelligence,
            $agg,
            $behavioralPattern,
            $psychologicalProfile,
            $driftProfile,
            $hiddenRiskFlag,
            $predictive
        );

        return [
            'cognitive_state' => $cognitiveState,
            'budget_health_score' => $budgetHealthScore,
            'behavioral_pattern' => $behavioralPattern,
            'psychological_profile' => $psychologicalProfile,
            'drift_profile' => $driftProfile,
            'dominant_force' => $dominantForce,
            'hidden_risk_flag' => $hiddenRiskFlag,
            'trajectory_vector' => $trajectoryVector,
            'meta_warning' => $metaWarning,
            'executive_summary' => $executiveSummary,
        ];
    }

    private function emptyCognition(): array
    {
        return [
            'cognitive_state' => self::STATE_INSUFFICIENT_DATA,
            'budget_health_score' => null,
            'behavioral_pattern' => null,
            'psychological_profile' => null,
            'drift_profile' => null,
            'dominant_force' => self::FORCE_UNCLEAR,
            'hidden_risk_flag' => false,
            'trajectory_vector' => 'stable',
            'meta_warning' => null,
            'executive_summary' => 'Chưa có ngưỡng ngân sách nào để đánh giá.',
        ];
    }

    private function resolveBehavioralConsistency(array $bi, array $agg): ?string
    {
        $discipline = $bi['discipline_score'] ?? null;
        $trend = $bi['discipline_trend'] ?? null;
        $bcsi = $bi['bcsi_stability_score'] ?? null;
        $correctionSpeed = $bi['correction_speed_index'] ?? null;
        $selfControl = $agg['avg_self_control_index'] ?? null;

        if ($discipline === null && $selfControl === null) {
            return null;
        }
        $d = $discipline ?? $selfControl ?? 50;
        $high = $d >= 70;
        $low = $d < 45;
        $fastCorrection = $correctionSpeed !== null && $correctionSpeed >= 60;
        $stableBcsi = $bcsi !== null && $bcsi >= 60;
        $improving = $trend === 'improving';

        if ($high && $stableBcsi && $improving) {
            return self::PATTERN_DISCIPLINED_STRUCTURAL;
        }
        if ($low && $fastCorrection) {
            return self::PATTERN_REACTIVE_CONTROLLER;
        }
        if (($d >= 45 && $d < 70) && ! $stableBcsi) {
            return self::PATTERN_VOLATILE_REGULATOR;
        }
        if ($high && ! $improving && ($bi['planning_realism_index'] ?? 100) < 50) {
            return self::PATTERN_ILLUSORY_DISCIPLINE;
        }

        return $high ? self::PATTERN_DISCIPLINED_STRUCTURAL : self::PATTERN_REACTIVE_CONTROLLER;
    }

    private function resolvePsychologicalBias(array $bi): ?string
    {
        $overconf = $bi['overconfidence_bias'] ?? null;
        $rational = $bi['rationalization_flag'] ?? false;
        $impulse = $bi['impulse_risk_score'] ?? null;
        $planning = $bi['planning_realism_index'] ?? null;

        if ($overconf === true || ($planning !== null && $planning < 40)) {
            return self::PROFILE_OVER_TIGHT;
        }
        if ($impulse !== null && $impulse >= 50) {
            return self::PROFILE_IMPULSE_PRONE;
        }
        if ($rational === true) {
            return self::PROFILE_SELF_JUSTIFYING;
        }
        if ($planning !== null && $planning >= 60 && ($impulse === null || $impulse < 40)) {
            return self::PROFILE_REALISTIC_PLANNER;
        }

        return null;
    }

    private function resolveDriftDetection(array $bi, array $thresholds): ?string
    {
        $driftIndex = $bi['budget_drift_index'] ?? null;
        $direction = $bi['budget_drift_direction'] ?? null;
        $severity = $bi['breach_severity_index'] ?? null;
        $varianceSum = 0;
        $n = 0;
        foreach ($thresholds as $t) {
            if (isset($t['historical_variance'])) {
                $varianceSum += (float) $t['historical_variance'];
                $n++;
            }
        }
        $avgVariance = $n > 0 ? $varianceSum / $n : null;

        if ($direction === 'stable' || ($driftIndex !== null && $driftIndex < 15)) {
            return self::DRIFT_STABLE;
        }
        if ($direction === 'increasing' && ($driftIndex === null || $driftIndex < 35)) {
            return self::DRIFT_GRADUAL;
        }
        if ($driftIndex !== null && $driftIndex >= 35) {
            return self::DRIFT_ACCELERATING;
        }
        if ($avgVariance !== null && $avgVariance > 20 && ($severity === null || $severity < 50)) {
            return self::DRIFT_STRUCTURAL_CREEP;
        }

        return $direction === 'stable' ? self::DRIFT_STABLE : self::DRIFT_GRADUAL;
    }

    private function resolveContextAlignment(array $bi, ?array $context): bool
    {
        $alignment = $bi['strategic_budget_alignment_score'] ?? null;
        $clarity = $bi['priority_clarity_index'] ?? null;
        if ($alignment !== null && $alignment >= 50) {
            return true;
        }
        if ($clarity !== null && $clarity >= 60) {
            return true;
        }
        return $alignment === null && $clarity === null;
    }

    private function resolvePredictive(array $bi): array
    {
        return [
            'predictive_breach_probability' => $bi['predictive_breach_probability'] ?? null,
            'reaction_delay_days' => $bi['reaction_delay_days'] ?? null,
            'correction_probability' => $bi['correction_probability'] ?? null,
        ];
    }

    private function resolveCognitiveState(
        array $bi,
        array $agg,
        ?string $behavioralPattern,
        ?string $psychologicalProfile,
        ?string $driftProfile,
        bool $alignmentOk
    ): string {
        $discipline = $bi['discipline_score'] ?? $agg['avg_self_control_index'] ?? null;
        $severity = $bi['breach_severity_index'] ?? null;
        $driftDir = $bi['budget_drift_direction'] ?? null;
        $driftIndex = $bi['budget_drift_index'] ?? null;
        $correctionSpeed = $bi['correction_speed_index'] ?? null;
        $alignment = $bi['strategic_budget_alignment_score'] ?? null;
        $predictive = $bi['predictive_breach_probability'] ?? null;
        $maxStreak = (int) ($agg['max_breach_streak'] ?? 0);

        $disciplineHigh = $discipline !== null && $discipline > 75;
        $severityLow = $severity === null || $severity < 40;
        $driftStable = $driftDir === 'stable';
        $correctionFast = $correctionSpeed !== null && $correctionSpeed >= 50;
        $alignmentHigh = $alignment !== null && $alignment >= 60;

        if ($disciplineHigh && $severityLow && $driftStable && $alignmentHigh) {
            return self::STATE_CONTROLLED_GROWTH;
        }

        $driftRising = $driftIndex !== null && $driftIndex >= 20;
        $planningLow = ($bi['planning_realism_index'] ?? 100) < 50;
        $impulseHigh = ($bi['impulse_risk_score'] ?? 0) >= 40;
        if ($driftRising && ($planningLow || $impulseHigh)) {
            return self::STATE_DRIFTING_STABILITY;
        }

        if ($maxStreak <= 2 && $correctionFast && $impulseHigh) {
            return self::STATE_REACTIVE_CONTROL;
        }

        $severityHigh = $severity !== null && $severity >= 60;
        $streakHigh = $maxStreak >= 3;
        $correctionSlow = $correctionSpeed !== null && $correctionSpeed < 40;
        $predictiveHigh = $predictive !== null && $predictive >= 0.35;
        if ($severityHigh || $streakHigh || $correctionSlow || $predictiveHigh) {
            return self::STATE_STRUCTURAL_STRESS;
        }

        return self::STATE_DRIFTING_STABILITY;
    }

    private function computeBudgetHealthScore(array $bi, array $agg, string $cognitiveState): int
    {
        $base = 50;
        $discipline = $bi['discipline_score'] ?? $agg['avg_self_control_index'] ?? null;
        if ($discipline !== null) {
            $base = (int) round($discipline);
        }
        $severity = $bi['breach_severity_index'] ?? null;
        if ($severity !== null && $severity > 50) {
            $base = max(0, $base - (int) round($severity / 3));
        }
        $drift = $bi['budget_drift_index'] ?? null;
        if ($drift !== null && $drift > 25) {
            $base = max(0, $base - 10);
        }
        $predictive = $bi['predictive_breach_probability'] ?? null;
        if ($predictive !== null && $predictive >= 0.4) {
            $base = max(0, $base - 15);
        }
        if ($cognitiveState === self::STATE_CONTROLLED_GROWTH) {
            $base = min(100, $base + 5);
        }
        if ($cognitiveState === self::STATE_STRUCTURAL_STRESS) {
            $base = min($base, 45);
        }
        return max(0, min(100, $base));
    }

    private function resolveDominantForce(array $bi, array $agg, array $thresholds, ?string $behavioralPattern): string
    {
        $discipline = $bi['discipline_score'] ?? $agg['avg_self_control_index'] ?? null;
        $maxStreak = (int) ($agg['max_breach_streak'] ?? 0);
        $correctionSpeed = $bi['correction_speed_index'] ?? null;
        $breachedCount = 0;
        foreach ($thresholds as $t) {
            if (! empty($t['breached'])) {
                $breachedCount++;
            }
        }
        $totalT = count($thresholds);
        $mostBreached = $totalT > 0 && $breachedCount >= $totalT * 0.5;

        if ($discipline !== null && $discipline >= 70 && $maxStreak === 0) {
            return self::FORCE_INTERNAL_CONTROL;
        }
        if ($mostBreached && $correctionSpeed !== null && $correctionSpeed >= 50 && $maxStreak <= 2) {
            return self::FORCE_EXTERNAL_CONSTRAINT;
        }
        if ($behavioralPattern === self::PATTERN_ILLUSORY_DISCIPLINE) {
            return self::FORCE_TIMING_EFFECT;
        }

        return self::FORCE_UNCLEAR;
    }

    private function detectHiddenRisk(array $bi, array $thresholds): bool
    {
        $planning = $bi['planning_realism_index'] ?? null;
        $impulse = $bi['impulse_risk_score'] ?? null;
        $anyBreached = false;
        foreach ($thresholds as $t) {
            if (! empty($t['breached'])) {
                $anyBreached = true;
                break;
            }
        }
        if ($anyBreached) {
            return false;
        }
        if ($planning !== null && $planning < 50 && $impulse !== null && $impulse >= 35) {
            return true;
        }
        return (bool) ($bi['behavior_mismatch_warning'] ?? false);
    }

    private function resolveTrajectory(array $bi): string
    {
        $trend = $bi['discipline_trend'] ?? null;
        if ($trend === 'improving') {
            return 'improving';
        }
        if ($trend === 'degrading') {
            return 'degrading';
        }
        $drift = $bi['budget_drift_direction'] ?? null;
        if ($drift === 'increasing') {
            return 'degrading';
        }
        return 'stable';
    }

    private function buildNarrative(
        string $cognitiveState,
        array $bi,
        array $agg,
        ?string $behavioralPattern,
        ?string $psychologicalProfile,
        ?string $driftProfile,
        bool $hiddenRiskFlag,
        array $predictive
    ): array {
        $executiveSummary = '';
        $metaWarning = null;
        $prob = $predictive['predictive_breach_probability'] ?? null;
        $probPct = $prob !== null ? (int) round($prob * 100) : null;
        $driftIndex = $bi['budget_drift_index'] ?? null;

        if ($cognitiveState === self::STATE_INSUFFICIENT_DATA) {
            $executiveSummary = 'Chưa có ngưỡng ngân sách nào để đánh giá.';
            return [$executiveSummary, $metaWarning];
        }

        if ($cognitiveState === self::STATE_CONTROLLED_GROWTH) {
            $executiveSummary = 'Bạn đang kiểm soát ngân sách tốt, kỷ luật và xu hướng ổn định.';
            if ($driftIndex !== null && $driftIndex >= 15) {
                $metaWarning = 'Xu hướng drift đang tăng nhẹ; nếu không điều chỉnh trong 1–2 kỳ tới, khả năng vượt ngưỡng có thể tăng.';
            }
            return [$executiveSummary, $metaWarning];
        }

        if ($cognitiveState === self::STATE_DRIFTING_STABILITY) {
            $executiveSummary = 'Ngân sách tạm ổn nhưng có dấu hiệu trôi (drift) hoặc kế hoạch chưa sát thực tế.';
            if ($probPct !== null && $probPct >= 25) {
                $metaWarning = "Nếu không điều chỉnh trong 2 kỳ tới, khả năng vượt ngưỡng tăng lên khoảng {$probPct}%.";
            }
            return [$executiveSummary, $metaWarning];
        }

        if ($cognitiveState === self::STATE_REACTIVE_CONTROL) {
            $executiveSummary = 'Bạn thường vượt ngưỡng nhưng có xu hướng chỉnh lại nhanh; kiểm soát mang tính phản ứng hơn là chủ động.';
            $metaWarning = 'Nên tăng dần kỷ luật chủ động để giảm lệ thuộc vào "chỉnh sau khi vượt".';
            return [$executiveSummary, $metaWarning];
        }

        if ($cognitiveState === self::STATE_STRUCTURAL_STRESS) {
            $executiveSummary = 'Ngân sách đang chịu áp lực: vượt ngưỡng nhiều, streak cao hoặc tốc độ chỉnh chậm.';
            if ($probPct !== null) {
                $metaWarning = "Xác suất vượt ngưỡng tháng tới ước tính khoảng {$probPct}%. Cần hành động sớm.";
            }
            return [$executiveSummary, $metaWarning];
        }

        if ($hiddenRiskFlag) {
            $metaWarning = 'Bạn chưa vượt ngưỡng, nhưng chỉ số planning_realism thấp và impulse_risk tăng. Đây là trạng thái kiểm soát bề mặt, chưa phải kiểm soát hành vi bền vững.';
            $executiveSummary = 'Tình hình ngân sách bề ngoài ổn, nhưng có rủi ro ẩn từ hành vi chi tiêu.';
            return [$executiveSummary, $metaWarning];
        }

        $executiveSummary = 'Đánh giá tổng hợp dựa trên ngưỡng ngân sách và hành vi chi tiêu.';
        return [$executiveSummary, $metaWarning];
    }
}
