<?php

namespace App\Services;

use App\Models\BudgetThreshold;
use App\Models\BudgetThresholdEvent;
use App\Models\BudgetThresholdSnapshot;
use App\Models\FinancialInsightFeedback;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Hệ đo kỷ luật tài chính từ ngưỡng ngân sách.
 * Trả budget_intelligence: DSI, BCSI, breach_severity, budget_drift, correction_speed,
 * strategic_alignment, priority_clarity, advice_adoption, predictive_breach_probability, ...
 */
class BudgetIntelligenceService
{
    private const LOOKBACK_MONTHS = 6;

    private const LOOKBACK_PLANNING = 3;

    private const BCSI_PERIODS = 6;

    private const ADVICE_ADOPTION_MONTHS = 6;

    public function __construct(
        private BudgetThresholdService $budgetThresholdService
    ) {}

    /**
     * Tính budget_intelligence từ threshold summary + snapshots + events.
     *
     * @param  array<string>  $linkedAccountNumbers
     * @param  array{active_count: int, thresholds: array, aggregate: array}  $thresholdSummary
     * @param  array{debt_stress_index?: int|float, surplus_positive?: bool, recommended_surplus_retention_pct?: int}|null  $context
     */
    public function compute(int $userId, array $linkedAccountNumbers, array $thresholdSummary, array $context = []): array
    {
        $activeCount = (int) ($thresholdSummary['active_count'] ?? 0);
        if ($activeCount === 0) {
            return $this->emptyIntelligence();
        }

        $thresholds = $thresholdSummary['thresholds'] ?? [];
        $thresholdIds = BudgetThreshold::where('user_id', $userId)->where('is_active', true)->pluck('id')->all();
        if (empty($thresholdIds)) {
            return $this->emptyIntelligence();
        }

        $now = Carbon::now();
        $from = $now->copy()->subMonths(self::LOOKBACK_MONTHS)->startOfDay();
        $snapshots = BudgetThresholdSnapshot::whereIn('budget_threshold_id', $thresholdIds)
            ->where('period_end', '>=', $from)
            ->orderByDesc('period_key')
            ->get();

        $dsi = $this->computeDSIBudget($snapshots);
        $planning = $this->computePlanningAccuracy($snapshots);
        $elasticity = $this->computeExpenseElasticity($snapshots);
        $goalMaturity = $this->computeGoalAlignmentSignal($thresholds, $thresholdSummary);
        $correctionSpeed = $this->computeCorrectionSpeed($userId, $thresholdIds);
        $reaction = $this->mapCorrectionSpeedToReaction($correctionSpeed);
        $heatmap = $this->computeCategoryHeatmap($snapshots, $thresholdIds);

        $bcsi = $this->computeBCSI($snapshots);
        $breachSeverity = $this->computeBreachSeverity($snapshots);
        $budgetDrift = $this->computeBudgetDrift($snapshots);
        $strategicAlignment = $this->computeStrategicBudgetAlignment($thresholds, $thresholdSummary, $context);
        $priorityClarity = $this->computePriorityClarityIndex($thresholds);
        $adviceAdoption = $this->computeAdviceAdoptionIndex($userId);
        $predictiveBreach = $this->computePredictiveBreachProbability(
            $dsi['discipline_score'] ?? null,
            $bcsi['bcsi_std_pct'] ?? null,
            $elasticity['elasticity_index'] ?? null,
            $thresholdSummary
        );

        $behaviorMismatchWarning = $this->shouldTriggerBehaviorMismatch(
            $dsi['impulse_risk_score'] ?? 0,
            $dsi['discipline_score'] ?? null,
            $planning['planning_realism_index'] ?? null
        );

        return [
            'discipline_score' => $dsi['discipline_score'],
            'discipline_trend' => $dsi['discipline_trend'],
            'planning_realism_index' => $planning['planning_realism_index'],
            'overconfidence_bias' => $planning['overconfidence_bias'],
            'elasticity_index' => $elasticity['elasticity_index'],
            'impulse_risk_score' => $dsi['impulse_risk_score'],
            'goal_maturity_score' => $goalMaturity,
            'reaction_delay_days' => $reaction['reaction_delay_days'],
            'correction_probability' => $reaction['correction_probability'],
            'habitual_breach_categories' => $heatmap['habitual_breach_categories'],
            'structural_vs_elastic_split' => $elasticity['structural_vs_elastic_split'],
            'behavior_mismatch_warning' => $behaviorMismatchWarning,
            'bcsi_std_pct' => $bcsi['bcsi_std_pct'],
            'bcsi_stability_score' => $bcsi['bcsi_stability_score'],
            'breach_severity_index' => $breachSeverity,
            'budget_drift_index' => $budgetDrift['budget_drift_index'],
            'budget_drift_direction' => $budgetDrift['budget_drift_direction'],
            'rationalization_flag' => $budgetDrift['rationalization_flag'],
            'correction_speed_median_days' => $correctionSpeed['correction_speed_median_days'],
            'correction_speed_index' => $correctionSpeed['correction_speed_index'],
            'strategic_budget_alignment_score' => $strategicAlignment,
            'priority_clarity_index' => $priorityClarity,
            'advice_adoption_index' => $adviceAdoption,
            'predictive_breach_probability' => $predictiveBreach,
        ];
    }

    private function emptyIntelligence(): array
    {
        return [
            'discipline_score' => null,
            'discipline_trend' => null,
            'planning_realism_index' => null,
            'overconfidence_bias' => null,
            'elasticity_index' => null,
            'impulse_risk_score' => null,
            'goal_maturity_score' => null,
            'reaction_delay_days' => null,
            'correction_probability' => null,
            'habitual_breach_categories' => [],
            'structural_vs_elastic_split' => null,
            'behavior_mismatch_warning' => false,
            'bcsi_std_pct' => null,
            'bcsi_stability_score' => null,
            'breach_severity_index' => null,
            'budget_drift_index' => null,
            'budget_drift_direction' => null,
            'rationalization_flag' => false,
            'correction_speed_median_days' => null,
            'correction_speed_index' => null,
            'strategic_budget_alignment_score' => null,
            'priority_clarity_index' => null,
            'advice_adoption_index' => null,
            'predictive_breach_probability' => null,
        ];
    }

    /**
     * 1. DSI-Budget: % kỳ đạt, dao động, breach >20%, streak → discipline_score 0–100, trend, impulse_risk.
     */
    private function computeDSIBudget(Collection $snapshots): array
    {
        if ($snapshots->isEmpty()) {
            return ['discipline_score' => null, 'discipline_trend' => null, 'impulse_risk_score' => null];
        }

        $total = $snapshots->count();
        $periodsMet = $snapshots->filter(fn ($s) => ! $s->breached)->count();
        $usageRatios = $snapshots->map(function ($s) {
            $limit = (int) ($s->amount_limit_vnd ?? 0);
            $spent = (int) ($s->total_spent_vnd ?? 0);
            return $limit > 0 ? $spent / $limit : 0.0;
        })->all();
        $variancePct = $this->variance($usageRatios) !== null ? round($this->variance($usageRatios) * 10000, 1) : 0;
        $breachOver20 = $snapshots->filter(fn ($s) => $s->breached && (float) ($s->deviation_pct ?? 0) > 20)->count();
        $maxStreak = $this->maxBreachStreakFromSnapshots($snapshots);

        $baseScore = $total > 0 ? 100 * ($periodsMet / $total) : 50;
        $penaltyVariance = min(25, $variancePct / 4);
        $penaltyBigBreach = min(20, $breachOver20 * 5);
        $penaltyStreak = min(30, $maxStreak * 10);
        $disciplineScore = max(0, min(100, round($baseScore - $penaltyVariance - $penaltyBigBreach - $penaltyStreak, 1)));

        $trend = $this->disciplineTrend($snapshots);
        $impulseRisk = min(100, round(($breachOver20 * 15 + $maxStreak * 20), 1));

        return [
            'discipline_score' => $disciplineScore,
            'discipline_trend' => $trend,
            'impulse_risk_score' => $impulseRisk,
        ];
    }

    private function maxBreachStreakFromSnapshots(Collection $snapshots): int
    {
        $max = 0;
        $current = 0;
        foreach ($snapshots as $s) {
            if ($s->breached) {
                $current++;
                $max = max($max, $current);
            } else {
                $current = 0;
            }
        }
        return $max;
    }

    private function disciplineTrend(Collection $snapshots): string
    {
        $n = $snapshots->count();
        if ($n < 4) {
            return 'stable';
        }
        $half = (int) floor($n / 2);
        $recent = $snapshots->take($half);
        $older = $snapshots->slice($half, $half);
        $recentMet = $recent->filter(fn ($s) => ! $s->breached)->count();
        $olderMet = $older->filter(fn ($s) => ! $s->breached)->count();
        $recentRate = $recent->count() > 0 ? $recentMet / $recent->count() : 0;
        $olderRate = $older->count() > 0 ? $olderMet / $older->count() : 0;
        if ($recentRate > $olderRate + 0.1) {
            return 'improving';
        }
        if ($recentRate < $olderRate - 0.1) {
            return 'declining';
        }
        $recentVar = $this->variance($recent->map(fn ($s) => (int) ($s->amount_limit_vnd ?? 0) > 0 ? (int) ($s->total_spent_vnd ?? 0) / (int) $s->amount_limit_vnd : 0)->all());
        if ($recentVar !== null && $recentVar > 0.05) {
            return 'volatile';
        }
        return 'stable';
    }

    /**
     * 2. Planning Accuracy: ngưỡng đặt vs chi trung bình 3 kỳ trước.
     */
    private function computePlanningAccuracy(Collection $snapshots): array
    {
        if ($snapshots->isEmpty()) {
            return ['planning_realism_index' => null, 'overconfidence_bias' => null];
        }

        $byThreshold = $snapshots->groupBy('budget_threshold_id');
        $realismSum = 0.0;
        $biasSum = 0.0;
        $count = 0;
        foreach ($byThreshold as $tid => $list) {
            $periods = $list->take(self::LOOKBACK_PLANNING);
            if ($periods->isEmpty()) {
                continue;
            }
            $avgSpent = $periods->avg('total_spent_vnd');
            $limit = (int) ($periods->first()->amount_limit_vnd ?? 0);
            if ($limit <= 0) {
                continue;
            }
            $ratio = $avgSpent / $limit;
            if ($ratio <= 0) {
                continue;
            }
            $planningRealism = $ratio >= 1 ? max(0, 100 - ($ratio - 1) * 30) : min(100, 70 + $ratio * 30);
            $overconfidenceBias = $ratio > 1.2 ? min(1, ($ratio - 1) / 2) : 0;
            $realismSum += $planningRealism;
            $biasSum += $overconfidenceBias;
            $count++;
        }
        if ($count === 0) {
            return ['planning_realism_index' => null, 'overconfidence_bias' => null];
        }
        return [
            'planning_realism_index' => round($realismSum / $count, 1),
            'overconfidence_bias' => round($biasSum / $count, 2),
        ];
    }

    /**
     * 3. Expense Elasticity: sau khi vượt, kỳ sau chi có giảm không.
     */
    private function computeExpenseElasticity(Collection $snapshots): array
    {
        if ($snapshots->count() < 2) {
            return ['elasticity_index' => null, 'structural_vs_elastic_split' => null];
        }

        $byThreshold = $snapshots->groupBy('budget_threshold_id');
        $elasticCount = 0;
        $totalBreachThenNext = 0;
        $structural = 0;
        $elastic = 0;
        foreach ($byThreshold as $tid => $list) {
            $ordered = $list->sortBy('period_key')->values();
            for ($i = 0; $i < $ordered->count() - 1; $i++) {
                if (! $ordered[$i]->breached) {
                    continue;
                }
                $totalBreachThenNext++;
                $spentCurrent = (int) ($ordered[$i]->total_spent_vnd ?? 0);
                $spentNext = (int) ($ordered[$i + 1]->total_spent_vnd ?? 0);
                if ($spentCurrent > 0 && $spentNext < $spentCurrent) {
                    $elasticCount++;
                    $elastic++;
                } else {
                    $structural++;
                }
            }
        }
        $elasticityIndex = $totalBreachThenNext > 0 ? round(100 * ($elasticCount / $totalBreachThenNext), 1) : null;
        $total = $structural + $elastic;
        $split = $total > 0 ? [
            'structural_pct' => round(100 * $structural / $total, 0),
            'elastic_pct' => round(100 * $elastic / $total, 0),
        ] : null;

        return [
            'elasticity_index' => $elasticityIndex,
            'structural_vs_elastic_split' => $split,
        ];
    }

    /**
     * 4. Goal Alignment: đơn giản từ số ngưỡng + tên (stub growth_orientation).
     */
    private function computeGoalAlignmentSignal(array $thresholds, array $thresholdSummary): ?float
    {
        if (empty($thresholds)) {
            return null;
        }
        $n = count($thresholds);
        $agg = $thresholdSummary['aggregate'] ?? [];
        $avgSpi = isset($agg['avg_self_control_index']) ? (float) $agg['avg_self_control_index'] : null;
        $goalMaturity = 50.0;
        if ($n >= 2) {
            $goalMaturity += min(25, ($n - 1) * 5);
        }
        if ($avgSpi !== null) {
            $goalMaturity = ($goalMaturity + $avgSpi) / 2;
        }
        return round(min(100, $goalMaturity), 1);
    }

    /** Map correction_speed → reaction_delay_days, correction_probability (tương thích cũ). */
    private function mapCorrectionSpeedToReaction(array $correctionSpeed): array
    {
        $median = $correctionSpeed['correction_speed_median_days'] ?? null;
        $index = $correctionSpeed['correction_speed_index'] ?? null;
        if ($median === null) {
            return ['reaction_delay_days' => null, 'correction_probability' => null];
        }
        $prob = $index !== null ? $index / 100 : ($median <= 14 ? min(1.0, 0.5 + (14 - $median) / 28) : max(0, 0.5 - ($median - 14) / 60));

        return [
            'reaction_delay_days' => $median,
            'correction_probability' => round($prob, 2),
        ];
    }

    /** BCSI: độ lệch chuẩn (% spent/limit) trong 6 kỳ. Thấp = ổn định, cao = cảm xúc. */
    private function computeBCSI(Collection $snapshots): array
    {
        if ($snapshots->isEmpty()) {
            return ['bcsi_std_pct' => null, 'bcsi_stability_score' => null];
        }
        $periodKeys = $snapshots->pluck('period_key')->unique()->sort()->values()->take(-self::BCSI_PERIODS)->all();
        $subset = $snapshots->filter(fn ($s) => in_array($s->period_key, $periodKeys, true));
        $ratios = $subset->map(function ($s) {
            $limit = (int) ($s->amount_limit_vnd ?? 0);
            $spent = (int) ($s->total_spent_vnd ?? 0);
            return $limit > 0 ? $spent / $limit : 0.0;
        })->all();
        $std = $this->stdDev($ratios);
        if ($std === null) {
            return ['bcsi_std_pct' => null, 'bcsi_stability_score' => null];
        }
        $bcsiStdPct = round($std * 100, 1);
        $bcsiStabilityScore = max(0, min(100, round(100 - min(100, $bcsiStdPct * 2), 1)));

        return [
            'bcsi_std_pct' => $bcsiStdPct,
            'bcsi_stability_score' => $bcsiStabilityScore,
        ];
    }

    /** Breach Severity: average(deviation_pct when breached). */
    private function computeBreachSeverity(Collection $snapshots): ?float
    {
        $breached = $snapshots->filter(fn ($s) => $s->breached);
        if ($breached->isEmpty()) {
            return null;
        }
        $sum = $breached->sum(fn ($s) => (float) ($s->deviation_pct ?? 0));

        return round($sum / $breached->count(), 1);
    }

    /** Budget Drift: limit tăng sau vượt = rationalization; giữ nguyên = kỷ luật yếu; giảm = điều chỉnh tốt. */
    private function computeBudgetDrift(Collection $snapshots): array
    {
        if ($snapshots->count() < 2) {
            return ['budget_drift_index' => null, 'budget_drift_direction' => null, 'rationalization_flag' => false];
        }
        $byThreshold = $snapshots->groupBy('budget_threshold_id');
        $limitIncreasedAfterBreach = 0;
        $breachCount = 0;
        $trendUp = 0;
        $trendDown = 0;
        foreach ($byThreshold as $tid => $list) {
            $ordered = $list->sortBy('period_key')->values();
            for ($i = 0; $i < $ordered->count() - 1; $i++) {
                if (! $ordered[$i]->breached) {
                    continue;
                }
                $breachCount++;
                $limitBefore = (int) ($ordered[$i]->amount_limit_vnd ?? 0);
                $limitAfter = (int) ($ordered[$i + 1]->amount_limit_vnd ?? 0);
                if ($limitAfter > $limitBefore) {
                    $limitIncreasedAfterBreach++;
                }
            }
            if ($ordered->count() >= 2) {
                $first = (int) ($ordered->first()->amount_limit_vnd ?? 0);
                $last = (int) ($ordered->last()->amount_limit_vnd ?? 0);
                if ($last > $first) {
                    $trendUp++;
                } elseif ($last < $first) {
                    $trendDown++;
                }
            }
        }
        $rationalizationFlag = $breachCount > 0 && $limitIncreasedAfterBreach / $breachCount >= 0.5;
        $direction = 'stable';
        if ($trendUp > $trendDown && $trendUp >= 1) {
            $direction = $rationalizationFlag ? 'rationalization' : 'increasing';
        } elseif ($trendDown > $trendUp && $trendDown >= 1) {
            $direction = 'improving';
        }
        $driftScore = 50;
        if ($direction === 'improving') {
            $driftScore = min(100, 50 + $trendDown * 15);
        } elseif ($direction === 'rationalization' || $direction === 'increasing') {
            $driftScore = max(0, 50 - $limitIncreasedAfterBreach * 10);
        }

        return [
            'budget_drift_index' => round($driftScore, 1),
            'budget_drift_direction' => $direction,
            'rationalization_flag' => $rationalizationFlag,
        ];
    }

    /** Correction Speed: median days from breach (period_evaluated breached) to threshold_updated. */
    private function computeCorrectionSpeed(int $userId, array $thresholdIds): array
    {
        $events = BudgetThresholdEvent::where('user_id', $userId)
            ->whereIn('budget_threshold_id', $thresholdIds)
            ->whereIn('event_type', ['period_evaluated', 'threshold_updated'])
            ->orderBy('created_at')
            ->get();

        $delays = [];
        $pendingByTid = [];
        foreach ($events as $e) {
            $tid = $e->budget_threshold_id;
            if ($tid === null) {
                continue;
            }
            $payload = $e->payload ?? [];
            if ($e->event_type === 'period_evaluated' && ($payload['breached'] ?? false)) {
                $pendingByTid[$tid] = $e->created_at;
            }
            if ($e->event_type === 'threshold_updated' && isset($pendingByTid[$tid])) {
                $delays[] = $pendingByTid[$tid]->diffInDays($e->created_at);
                unset($pendingByTid[$tid]);
            }
        }
        if (empty($delays)) {
            return ['correction_speed_median_days' => null, 'correction_speed_index' => null];
        }
        sort($delays);
        $n = count($delays);
        $median = $n % 2 === 1 ? $delays[(int) floor($n / 2)] : ($delays[$n / 2 - 1] + $delays[$n / 2]) / 2;
        $index = $median <= 7 ? 100 : ($median <= 14 ? max(0, 100 - ($median - 7) * 5) : max(0, 65 - ($median - 14) * 2));

        return [
            'correction_speed_median_days' => (int) round($median),
            'correction_speed_index' => round(min(100, $index), 1),
        ];
    }

    /** Strategic Budget Alignment: ngưỡng chi vs surplus/debt. */
    private function computeStrategicBudgetAlignment(array $thresholds, array $thresholdSummary, array $context): ?float
    {
        if (empty($thresholds)) {
            return null;
        }
        $totalLimits = (int) array_sum(array_column($thresholds, 'limit_vnd'));
        $debtStress = isset($context['debt_stress_index']) ? (float) $context['debt_stress_index'] : null;
        $surplusPositive = $context['surplus_positive'] ?? null;
        $alignment = 70.0;
        if ($debtStress !== null && $debtStress > 60 && $totalLimits > 5_000_000) {
            $alignment -= min(40, ($debtStress - 60) / 2);
        }
        if ($surplusPositive === false && $totalLimits > 10_000_000) {
            $alignment -= 15;
        }

        return round(max(0, min(100, $alignment)), 1);
    }

    /** Priority Clarity: ít ngưỡng rõ ràng = trưởng thành; nhiều nhỏ lẻ = cảm xúc. */
    private function computePriorityClarityIndex(array $thresholds): ?float
    {
        $n = count($thresholds);
        if ($n === 0) {
            return null;
        }
        if ($n <= 3) {
            return 100.0;
        }
        if ($n <= 6) {
            return max(0, 100 - ($n - 3) * 10);
        }

        return max(0, 70 - ($n - 6) * 5);
    }

    /** Advice Adoption: tỷ lệ đồng ý với đề xuất (agree / (agree+infeasible)) trong 6 tháng. */
    private function computeAdviceAdoptionIndex(int $userId): ?float
    {
        $from = Carbon::now()->subMonths(self::ADVICE_ADOPTION_MONTHS);
        $feedback = FinancialInsightFeedback::where('user_id', $userId)
            ->where('created_at', '>=', $from)
            ->get();
        $agree = $feedback->where('feedback_type', FinancialInsightFeedback::TYPE_AGREE)->count();
        $infeasible = $feedback->where('feedback_type', FinancialInsightFeedback::TYPE_INFEASIBLE)->count();
        $total = $agree + $infeasible;
        if ($total === 0) {
            return null;
        }

        return round(100 * $agree / $total, 1);
    }

    /** Predictive Breach Probability: P(breach trong 30 ngày) từ discipline, volatility, elasticity, velocity. */
    private function computePredictiveBreachProbability(
        ?float $disciplineScore,
        ?float $bcsiStdPct,
        ?float $elasticityIndex,
        array $thresholdSummary
    ): ?float {
        $thresholds = $thresholdSummary['thresholds'] ?? [];
        if (empty($thresholds)) {
            return null;
        }
        $currentVelocity = 0.0;
        $count = 0;
        foreach ($thresholds as $t) {
            $limit = (int) ($t['limit_vnd'] ?? 0);
            if ($limit <= 0) {
                continue;
            }
            $spent = (int) ($t['spent_vnd'] ?? 0);
            $currentVelocity += $spent / $limit;
            $count++;
        }
        $currentVelocity = $count > 0 ? $currentVelocity / $count : 0.0;
        $base = 0.2;
        if ($disciplineScore !== null) {
            $base += (100 - $disciplineScore) / 250;
        }
        if ($bcsiStdPct !== null) {
            $base += min(0.25, $bcsiStdPct / 400);
        }
        if ($elasticityIndex !== null && $elasticityIndex < 50) {
            $base += 0.1;
        }
        if ($currentVelocity > 0.9) {
            $base += min(0.3, ($currentVelocity - 0.9) * 2);
        }

        return round(max(0, min(1, $base)), 2);
    }

    private function stdDev(array $values): ?float
    {
        $n = count($values);
        if ($n < 2) {
            return null;
        }
        $mean = array_sum($values) / $n;
        $sumSq = 0.0;
        foreach ($values as $v) {
            $sumSq += ($v - $mean) ** 2;
        }

        return (float) sqrt($sumSq / ($n - 1));
    }

    /**
     * 6. Category Emotional Heatmap: nhóm nào hay vượt.
     */
    private function computeCategoryHeatmap(Collection $snapshots, array $thresholdIds): array
    {
        if ($snapshots->isEmpty()) {
            return ['habitual_breach_categories' => []];
        }
        $thresholds = BudgetThreshold::whereIn('id', $thresholdIds)->get()->keyBy('id');
        $byThreshold = $snapshots->groupBy('budget_threshold_id');
        $habitual = [];
        foreach ($byThreshold as $tid => $list) {
            $t = $thresholds->get($tid);
            $name = $t ? $t->name : 'Ngưỡng#' . $tid;
            $total = $list->count();
            $breachCount = $list->filter(fn ($s) => $s->breached)->count();
            $habitual[] = [
                'name' => $name,
                'breach_rate' => $total > 0 ? round(100 * $breachCount / $total, 1) : 0,
                'breach_count' => $breachCount,
                'period_count' => $total,
            ];
        }
        usort($habitual, fn ($a, $b) => ($b['breach_rate'] ?? 0) <=> ($a['breach_rate'] ?? 0));
        return ['habitual_breach_categories' => array_slice($habitual, 0, 10)];
    }

    private function shouldTriggerBehaviorMismatch(float $impulseRisk, ?float $disciplineScore, ?float $planningRealism): bool
    {
        if ($impulseRisk >= 50 && $disciplineScore !== null && $disciplineScore < 50) {
            return true;
        }
        if ($planningRealism !== null && $planningRealism < 40) {
            return true;
        }
        return false;
    }

    private function variance(array $values): ?float
    {
        $n = count($values);
        if ($n < 2) {
            return null;
        }
        $mean = array_sum($values) / $n;
        $sumSq = 0.0;
        foreach ($values as $v) {
            $sumSq += ($v - $mean) ** 2;
        }
        return (float) ($sumSq / ($n - 1));
    }
}
