<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Context cho form tạo công việc: focus window, workload, execution stage, best time, task fit, capacity.
 * Tầng 1 — Context Awareness: task creation biết execution context.
 */
class TaskCreationContextService
{
    /**
     * @param  array{focus: \Illuminate\Support\Collection, total_planned_minutes: int, available_minutes: int}  $focusPlan
     * @param  array{planned_today: int, completed_today: int, focus_rate_today: float|null}|null  $executionMetrics
     * @param  array{completion_by_hour?: array<int, int>, profile_label?: string}|null  $behaviorProfile
     * @param  array{risk_tier?: string, risk_score?: float}|null  $failureDetection
     * @param  array{execution_stage?: string, stage?: string}|null  $insightPayload
     * @return array{focus_window: string, workload_pct: int, suggested_priority: string|null, suggested_priority_value: int|null, best_time: string|null, execution_stage: string, risk_tier: string, overload_hint: string|null, capacity_remaining_minutes: int, task_fit_score: int}
     */
    public function build(
        array $focusPlan,
        ?array $executionMetrics,
        ?array $behaviorProfile,
        ?array $failureDetection,
        ?array $insightPayload
    ): array {
        $focusWindow = $this->deriveFocusWindow($behaviorProfile);
        $availableMinutes = max(1, $focusPlan['available_minutes'] ?? 120);
        $plannedMinutes = $focusPlan['total_planned_minutes'] ?? 0;
        $workloadPct = (int) round(min(100, ($plannedMinutes / $availableMinutes) * 100));
        $capacityRemaining = max(0, $availableMinutes - $plannedMinutes);

        $executionStage = $insightPayload['execution_stage'] ?? 'planning';
        $riskTier = $failureDetection['risk_tier'] ?? 'normal';
        $overloadHint = $executionStage === 'overload'
            ? 'Nên tạo task nhỏ (<30 phút)'
            : null;

        [$suggestedLabel, $suggestedValue] = $this->suggestPriorityFromContext($executionStage, $riskTier);
        $bestTime = $this->deriveBestTime($focusWindow, $availableMinutes, $plannedMinutes);
        $taskFitScore = $this->computeTaskFitScore($availableMinutes, $plannedMinutes, $executionStage, $suggestedValue);

        return [
            'focus_window' => $focusWindow,
            'workload_pct' => $workloadPct,
            'suggested_priority' => $suggestedLabel,
            'suggested_priority_value' => $suggestedValue,
            'best_time' => $bestTime,
            'execution_stage' => $executionStage,
            'risk_tier' => $riskTier,
            'overload_hint' => $overloadHint,
            'capacity_remaining_minutes' => $capacityRemaining,
            'task_fit_score' => $taskFitScore,
        ];
    }

    protected function deriveFocusWindow(?array $behaviorProfile): string
    {
        if (empty($behaviorProfile['completion_by_hour']) || ! is_array($behaviorProfile['completion_by_hour'])) {
            return '—';
        }
        $byHour = $behaviorProfile['completion_by_hour'];
        $max = 0;
        $peakStart = null;
        $peakEnd = null;
        foreach ($byHour as $h => $cnt) {
            if ($cnt > $max) {
                $max = $cnt;
                $peakStart = $h;
                $peakEnd = $h;
            }
        }
        if ($max > 0 && $peakStart !== null) {
            for ($h = $peakStart - 1; $h >= 0 && ($byHour[$h] ?? 0) >= $max * 0.5; $h--) {
                $peakStart = $h;
            }
            for ($h = $peakEnd + 1; $h < 24 && ($byHour[$h] ?? 0) >= $max * 0.5; $h++) {
                $peakEnd = $h;
            }
            return sprintf('%02d:00–%02d:00', $peakStart, min(23, $peakEnd + 1));
        }
        return '—';
    }

    /** Gợi ý priority theo stage/risk: overload → ưu tiên thấp/trung bình; execution → cao. */
    protected function suggestPriorityFromContext(string $executionStage, string $riskTier): array
    {
        if ($executionStage === 'overload') {
            return ['Trung bình', 3];
        }
        if ($executionStage === 'recovery') {
            return ['Trung bình', 3];
        }
        if ($executionStage === 'execution' && $riskTier === 'normal') {
            return ['Cao', 2];
        }
        return [null, null];
    }

    /**
     * Best time = giữa focus window. Nếu cửa sổ đã qua (current_time > end) → now + 5 phút.
     */
    protected function deriveBestTime(string $focusWindow, int $availableMinutes, int $plannedMinutes): ?string
    {
        $now = Carbon::now('Asia/Ho_Chi_Minh');
        $currentMin = $now->hour * 60 + $now->minute;

        if ($focusWindow === '—') {
            $next = $now->copy()->addMinutes(5);
            return sprintf('%02d:%02d', $next->hour, $next->minute);
        }
        if (! preg_match('/^(\d{1,2}):\d{2}[–-](\d{1,2}):\d{2}$/', $focusWindow, $m)) {
            return null;
        }
        $start = (int) $m[1];
        $end = (int) $m[2];
        $endMin = $end * 60;
        if ($currentMin >= $endMin) {
            $next = $now->copy()->addMinutes(5);
            return sprintf('%02d:%02d', $next->hour, $next->minute);
        }
        $mid = ($start + $end) / 2;
        $hour = (int) floor($mid);
        $min = (abs($mid - round($mid)) < 0.01) ? 0 : 30;
        return sprintf('%02d:%02d', $hour, $min);
    }

    /** Task Fit Score 0–100: workload_fit*0.4 + duration_fit*0.3 + priority_alignment*0.3 (giả định task mới). */
    protected function computeTaskFitScore(int $availableMinutes, int $plannedMinutes, string $executionStage, ?int $suggestedPriorityValue): int
    {
        $workloadFit = $availableMinutes > 0
            ? min(1.0, max(0, ($availableMinutes - $plannedMinutes) / $availableMinutes))
            : 0.5;
        $durationFit = $executionStage === 'overload' ? 0.6 : 1.0;
        $priorityAlignment = $suggestedPriorityValue !== null ? 1.0 : 0.5;
        $raw = $workloadFit * 0.4 + $durationFit * 0.3 + $priorityAlignment * 0.3;
        return (int) round(min(100, max(0, $raw * 100)));
    }
}
