<?php

namespace App\Services;

use App\Models\WorkTaskInstance;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Behavior Drift Detection — phát hiện khi hành vi lệch khỏi routine đã học.
 * So sánh long_window (30 lần) vs short_window (5 lần); drift > ngưỡng và short ổn định → drift.
 */
class BehaviorDriftService
{
    protected function config(string $key, $default)
    {
        return config('behavior_intelligence.behavior_drift.' . $key, $default);
    }

    protected function minuteOfDay(?Carbon $completedAt): ?int
    {
        if (! $completedAt) {
            return null;
        }
        $t = $completedAt->copy()->setTimezone('Asia/Ho_Chi_Minh');

        return $t->hour * 60 + $t->minute;
    }

    protected function median(Collection $values): float
    {
        $sorted = $values->sort()->values();
        $n = $sorted->count();
        if ($n === 0) {
            return 0.0;
        }
        if ($n % 2 === 1) {
            return (float) $sorted->get((int) floor($n / 2));
        }

        return ($sorted->get($n / 2 - 1) + $sorted->get($n / 2)) / 2.0;
    }

    protected function stdDev(Collection $values): float
    {
        $n = $values->count();
        if ($n < 2) {
            return 0.0;
        }
        $mean = $values->avg();
        $variance = $values->map(fn ($x) => ($x - $mean) ** 2)->avg();

        return sqrt($variance);
    }

    /**
     * Drift cho một task: so sánh median(long_window) vs median(short_window).
     *
     * @return array{drift_detected: bool, median_long: float, median_short: float, drift_minutes: float, variance_short: float, is_temporary: bool, drift_confidence: float, median_short_time: string}|null
     */
    public function getDriftForTask(int $taskId): ?array
    {
        $longSize = (int) $this->config('long_window', 30);
        $shortSize = (int) $this->config('short_window', 5);
        $threshold = (float) $this->config('threshold_minutes', 45);
        $varianceMax = (float) $this->config('short_variance_max', 60);
        $routineDecay = (float) $this->config('routine_decay_on_drift', 0.7);

        $instances = WorkTaskInstance::where('work_task_id', $taskId)
            ->where('status', WorkTaskInstance::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->limit($longSize)
            ->get();

        $minutes = $instances->map(fn ($i) => $this->minuteOfDay($i->completed_at))->filter(fn ($m) => $m !== null)->values();
        if ($minutes->count() < $shortSize) {
            return null;
        }

        $longWindow = $minutes->take($longSize)->values();
        $shortWindow = $minutes->take($shortSize)->values();
        $medianLong = $this->median($longWindow);
        $medianShort = $this->median($shortWindow);
        $driftMinutes = abs($medianShort - $medianLong);
        $varianceShort = $shortWindow->count() >= 2 ? $this->stdDev($shortWindow) : 0.0;
        $isTemporary = $varianceShort > $varianceMax;
        $driftDetected = $driftMinutes > $threshold && ! $isTemporary;

        $shortConsistency = max(0.0, 1.0 - ($varianceShort / $varianceMax));
        $driftMagnitude = min(1.0, $driftMinutes / 120.0);
        $driftConfidence = $shortConsistency * $driftMagnitude;

        $medianShortTime = sprintf('%02d:%02d', (int) floor($medianShort / 60) % 24, (int) ($medianShort % 60));

        return [
            'drift_detected' => $driftDetected,
            'median_long' => round($medianLong, 2),
            'median_short' => round($medianShort, 2),
            'drift_minutes' => round($driftMinutes, 2),
            'variance_short' => round($varianceShort, 2),
            'is_temporary' => $isTemporary,
            'drift_confidence' => round($driftConfidence, 4),
            'median_short_time' => $medianShortTime,
            'routine_decay' => $driftDetected ? $routineDecay : 1.0,
        ];
    }

    /**
     * Drift cho tất cả task của user (chỉ task có drift_detected).
     *
     * @return array<int, array> task_id => drift info
     */
    public function getDriftForUser(int $userId): array
    {
        $taskIds = WorkTaskInstance::whereHas('task', fn ($q) => $q->where('user_id', $userId))
            ->where('status', WorkTaskInstance::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->distinct()
            ->pluck('work_task_id');

        $out = [];
        foreach ($taskIds as $taskId) {
            $d = $this->getDriftForTask($taskId);
            if ($d && $d['drift_detected']) {
                $out[$taskId] = $d;
            }
        }

        return $out;
    }
}
