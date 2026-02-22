<?php

namespace App\Services;

use App\Models\FinancialStateSnapshot;
use App\Models\User;
use Carbon\Carbon;

/**
 * Narrative Memory Layer: biến dữ liệu rời rạc thành "hành trình" (behavior evolution, strategy transition, recurring pattern, progress curve).
 * Output inject vào cognitive_input.narrative_memory để GPT bắt buộc dùng.
 */
class UserNarrativeMemoryBuilder
{
    /** Half-life ngày cho decay: feedback/snapshot càng cũ càng ít trọng số. */
    private const DECAY_HALFLIFE_DAYS = 90;

    /**
     * Xây narrative_memory từ snapshots, drift, strategy profile, behavior profile.
     *
     * @param  array<int, FinancialStateSnapshot>  $snapshots  Chronological (cũ → mới), tối đa 6
     * @param  array{dsi_series: array, buffer_series: array, summary: string, repeated_high_dsi: bool, feedback_count_increase: int, structural_state_changed: bool}  $driftSignals
     * @param  array{total_feedback_count: int, reject_expense_solution_ratio: float, reject_income_solution_ratio: float, last_5_feedback_summary?: array}  $strategyProfile
     * @param  array{execution_consistency_score?: float, execution_consistency_score_reduce_expense?: float, surplus_usage_pattern?: string}|null  $behaviorProfile  Payload array
     */
    public function build(
        User $user,
        array $snapshots,
        array $driftSignals,
        array $strategyProfile,
        ?array $behaviorProfile,
        array $currentState
    ): array {
        $behaviorEvolution = $this->buildBehaviorEvolutionSummary($driftSignals, $strategyProfile, $behaviorProfile);
        $strategyTransition = $this->buildStrategyTransitionSummary($snapshots, $currentState, $driftSignals);
        $recurringPattern = $this->buildRecurringPatternSummary($strategyProfile, $driftSignals, $behaviorProfile);
        $progressCurve = $this->buildProgressCurveSummary($driftSignals, $snapshots, $behaviorProfile);
        $trustLevel = $this->computeTrustLevel($behaviorProfile, $strategyProfile);
        $behaviorCurve = $this->buildBehaviorCurve($snapshots, $strategyProfile, $behaviorProfile, $driftSignals);

        return [
            'narrative_memory' => [
                'behavior_evolution_summary' => $behaviorEvolution,
                'strategy_transition_summary' => $strategyTransition,
                'recurring_pattern_summary' => $recurringPattern,
                'progress_curve_summary' => $progressCurve,
                'trust_level' => $trustLevel,
                'behavior_curve' => $behaviorCurve,
            ],
        ];
    }

    private function buildBehaviorEvolutionSummary(array $driftSignals, array $strategyProfile, ?array $behaviorProfile): string
    {
        $parts = [];
        $summary = trim((string) ($driftSignals['summary'] ?? ''));
        if ($summary !== '') {
            $parts[] = $summary;
        }
        $feedbackIncrease = (int) ($driftSignals['feedback_count_increase'] ?? 0);
        if ($feedbackIncrease > 0) {
            $parts[] = sprintf('Bạn %d lần báo "không khả thi" hoặc "không đúng" với đề xuất so với kỳ trước.', $feedbackIncrease);
        }
        $repeatedHighDsi = ! empty($driftSignals['repeated_high_dsi']);
        if ($repeatedHighDsi) {
            $parts[] = 'Mức stress nợ cao đã lặp lại nhiều kỳ.';
        }
        $currentConsistency = $behaviorProfile !== null && isset($behaviorProfile['execution_consistency_score'])
            ? (float) $behaviorProfile['execution_consistency_score'] : null;
        if ($currentConsistency !== null) {
            $parts[] = sprintf('Điểm tuân thủ đề xuất hiện tại: **%d**/100.', (int) round($currentConsistency));
        }
        $totalFeedback = (int) ($strategyProfile['total_feedback_count'] ?? 0);
        if ($totalFeedback > 0) {
            $rejectExpense = (float) ($strategyProfile['reject_expense_solution_ratio'] ?? 0);
            if ($rejectExpense > 0.3) {
                $parts[] = sprintf('Tỷ lệ từ chối đề xuất giảm chi trong lịch sử phản hồi: **%d**%%.', (int) round($rejectExpense * 100));
            }
        }
        return $parts === [] ? '' : implode(' ', $parts);
    }

    /**
     * @param  array<int, FinancialStateSnapshot>  $snapshots
     */
    private function buildStrategyTransitionSummary(array $snapshots, array $currentState, array $driftSignals): array
    {
        $changed = ! empty($driftSignals['structural_state_changed']);
        $prevObjective = null;
        $prevMode = null;
        $prevStateKey = null;
        $currentMode = $currentState['brain_mode_key'] ?? null;
        $currentStateKey = $currentState['structural_state']['key'] ?? null;
        $currentObjective = $currentState['objective']['key'] ?? null;

        if (count($snapshots) >= 2) {
            $oldest = $snapshots[0];
            $prevObjective = is_array($oldest->objective) ? ($oldest->objective['key'] ?? null) : null;
            $prevMode = $oldest->brain_mode_key;
            $prevStateKey = is_array($oldest->structural_state) ? ($oldest->structural_state['key'] ?? null) : null;
        }

        $modeChanged = $prevMode !== null && $currentMode !== null && $prevMode !== $currentMode;
        $objectiveChanged = $prevObjective !== null && $currentObjective !== null && $prevObjective !== $currentObjective;
        $anyChanged = $changed || $modeChanged || $objectiveChanged;

        $summary = '';
        if ($modeChanged) {
            $summary = sprintf('Bạn chuyển từ chế độ **%s** sang **%s** trong các kỳ gần đây.', $prevMode ?? '—', $currentMode ?? '—');
        }
        if ($objectiveChanged && $summary !== '') {
            $summary .= ' ';
        }
        if ($objectiveChanged) {
            $summary .= sprintf('Mục tiêu từ **%s** sang **%s**.', $prevObjective ?? '—', $currentObjective ?? '—');
        }
        if ($changed && $summary !== '') {
            $summary .= ' ';
        }
        if ($changed) {
            $summary .= 'Trạng thái cấu trúc (phase) đã thay đổi.';
        }
        if ($summary === '' && ! $anyChanged) {
            $summary = 'Không có thay đổi chiến lược hoặc chế độ rõ rệt trong các kỳ gần đây.';
        }

        return [
            'changed' => $anyChanged,
            'summary' => $summary,
            'previous_mode' => $prevMode,
            'current_mode' => $currentMode,
            'previous_objective' => $prevObjective,
            'current_objective' => $currentObjective,
        ];
    }

    private function buildRecurringPatternSummary(array $strategyProfile, array $driftSignals, ?array $behaviorProfile): string
    {
        $parts = [];
        $rejectExpense = (float) ($strategyProfile['reject_expense_solution_ratio'] ?? 0);
        if ($rejectExpense >= 0.5) {
            $parts[] = 'Bạn thường xuyên báo "không khả thi" với đề xuất giảm chi.';
        }
        if (! empty($driftSignals['repeated_high_dsi'])) {
            $parts[] = 'DSI cao lặp lại nhiều kỳ — pattern rủi ro nợ dai dẳng.';
        }
        $surplus = $behaviorProfile['surplus_usage_pattern'] ?? null;
        if ($surplus === 'spend') {
            $parts[] = 'Mỗi khi có thặng dư, bạn có xu hướng tăng chi thay vì tăng buffer hoặc trả nợ.';
        }
        if ($surplus === 'mixed') {
            $parts[] = 'Thặng dư được phân bổ cả buffer và chi — chưa có pattern rõ.';
        }
        return implode(' ', $parts);
    }

    /**
     * @param  array<int, FinancialStateSnapshot>  $snapshots
     */
    private function buildProgressCurveSummary(array $driftSignals, array $snapshots, ?array $behaviorProfile): string
    {
        $parts = [];
        $dsiSeries = $driftSignals['dsi_series'] ?? [];
        $bufferSeries = $driftSignals['buffer_series'] ?? [];
        $dsiTrend = $driftSignals['dsi_trend'] ?? 'stable';
        $bufferTrend = $driftSignals['buffer_trend'] ?? 'stable';
        $n = count($dsiSeries);
        if ($n >= 2) {
            $first = $dsiSeries[0];
            $last = $dsiSeries[$n - 1];
            if ($dsiTrend === 'improving') {
                $parts[] = sprintf('DSI giảm từ **%d** xuống **%d** trong %d kỳ gần đây.', $first, $last, $n);
            } elseif ($dsiTrend === 'worsening') {
                $parts[] = sprintf('DSI tăng từ **%d** lên **%d** trong %d kỳ gần đây.', $first, $last, $n);
            }
        }
        $bn = count($bufferSeries);
        if ($bn >= 2) {
            $bfirst = $bufferSeries[0];
            $blast = $bufferSeries[$bn - 1];
            if ($bufferTrend === 'improving') {
                $parts[] = sprintf('Buffer tăng từ **%d** lên **%d** tháng.', $bfirst, $blast);
            } elseif ($bufferTrend === 'worsening') {
                $parts[] = sprintf('Buffer giảm từ **%d** xuống **%d** tháng.', $bfirst, $blast);
            }
        }
        $consistency = $behaviorProfile['execution_consistency_score'] ?? null;
        if ($consistency !== null) {
            $parts[] = sprintf('Điểm tuân thủ đề xuất hiện tại: **%d**/100.', (int) round((float) $consistency));
        }
        return implode(' ', $parts);
    }

    private function computeTrustLevel(?array $behaviorProfile, array $strategyProfile): string
    {
        $consistency = $behaviorProfile !== null && isset($behaviorProfile['execution_consistency_score'])
            ? (float) $behaviorProfile['execution_consistency_score'] : 50.0;
        $rejectExpense = (float) ($strategyProfile['reject_expense_solution_ratio'] ?? 0);
        $totalFeedback = (int) ($strategyProfile['total_feedback_count'] ?? 0);

        if ($totalFeedback >= 3 && ($rejectExpense >= 0.6 || $consistency < 40)) {
            return 'low';
        }
        if ($consistency >= 70 && $rejectExpense <= 0.3) {
            return 'high';
        }
        return 'medium';
    }

    /**
     * behavior_curve: execution_consistency_series, reject_ratio_series, dsi_series, buffer_series.
     *
     * @param  array<int, FinancialStateSnapshot>  $snapshots
     * @param  array{dsi_series?: array, buffer_series?: array}  $driftSignals
     */
    private function buildBehaviorCurve(array $snapshots, array $strategyProfile, ?array $behaviorProfile, array $driftSignals = []): array
    {
        $executionSeries = [];
        foreach ($snapshots as $s) {
            if (isset($s->execution_consistency_score) && $s->execution_consistency_score !== null) {
                $executionSeries[] = (float) $s->execution_consistency_score;
            }
        }
        $current = $behaviorProfile['execution_consistency_score'] ?? null;
        if ($current !== null) {
            $executionSeries[] = (float) $current;
        }
        if ($executionSeries === [] && $current !== null) {
            $executionSeries = [(float) $current];
        }

        $rejectRatioSeries = [];
        $last5 = $strategyProfile['last_5_feedback_summary'] ?? [];
        $cumulative = 0;
        $count = 0;
        foreach ($last5 as $f) {
            $count++;
            $type = is_array($f) ? ($f['feedback_type'] ?? '') : '';
            $reason = is_array($f) ? ($f['reason_code'] ?? '') : '';
            if ($type === 'infeasible' && $reason === 'cannot_reduce_expense') {
                $cumulative++;
            }
            $rejectRatioSeries[] = $count > 0 ? round($cumulative / $count, 2) : 0.0;
        }

        return [
            'execution_consistency_series' => array_slice($executionSeries, -6),
            'reject_ratio_series' => array_slice($rejectRatioSeries, -6),
            'dsi_series' => array_slice($driftSignals['dsi_series'] ?? [], -6),
            'buffer_series' => array_slice($driftSignals['buffer_series'] ?? [], -6),
        ];
    }

    /** Decay weight: càng cũ càng nhẹ. weight = exp(-lambda * days_ago), half-life 90 ngày. */
    public static function decayWeight(Carbon $from, Carbon $to): float
    {
        $daysAgo = max(0, $from->diffInDays($to, false));
        $lambda = log(2) / self::DECAY_HALFLIFE_DAYS;
        return exp(-$lambda * $daysAgo);
    }
}
