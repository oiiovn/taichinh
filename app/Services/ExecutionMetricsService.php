<?php

namespace App\Services;

use App\Models\CongViecTask;
use App\Models\WorkTaskInstance;
use Carbon\Carbon;

/**
 * Execution Metrics Dashboard: execution_rate, focus_rate, delay_index, burnout_index.
 * Dùng cho AI coaching, habit tracking, gamification.
 */
class ExecutionMetricsService
{
    /**
     * @return array{
     *   execution_rate_7d: float,
     *   execution_rate_30d: float,
     *   focus_rate_today: float|null,
     *   delay_index: float,
     *   burnout_index: float,
     *   completed_today: int,
     *   planned_today: int,
     *   risk_tier: string
     * }
     */
    public function getMetrics(
        int $userId,
        ?array $behaviorProfile = null,
        ?array $failureDetection = null,
        int $plannedTodayCount = 0,
        int $completedTodayCount = 0,
        int $focusPlannedCount = 0,
        int $focusCompletedCount = 0
    ): array {
        $today = Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d');
        $from7 = Carbon::now('Asia/Ho_Chi_Minh')->subDays(7)->format('Y-m-d');
        $from30 = Carbon::now('Asia/Ho_Chi_Minh')->subDays(30)->format('Y-m-d');

        $taskIds = CongViecTask::where('user_id', $userId)->pluck('id')->all();
        if (empty($taskIds)) {
            return $this->emptyMetrics();
        }

        $instances7 = WorkTaskInstance::whereIn('work_task_id', $taskIds)
            ->whereBetween('instance_date', [$from7, $today])
            ->get();
        $instances30 = WorkTaskInstance::whereIn('work_task_id', $taskIds)
            ->whereBetween('instance_date', [$from30, $today])
            ->get();

        $total7 = $instances7->count();
        $total30 = $instances30->count();
        $completed7 = $instances7->where('status', WorkTaskInstance::STATUS_COMPLETED)->count();
        $completed30 = $instances30->where('status', WorkTaskInstance::STATUS_COMPLETED)->count();

        $executionRate7 = $total7 > 0 ? round($completed7 / $total7, 4) : 0.0;
        $executionRate30 = $total30 > 0 ? round($completed30 / $total30, 4) : 0.0;

        $focusRateToday = null;
        if ($focusPlannedCount > 0) {
            $focusRateToday = round($focusCompletedCount / $focusPlannedCount, 4);
        }

        $delayIndex = 0.0;
        if ($behaviorProfile !== null && isset($behaviorProfile['avg_delay_days']) && $behaviorProfile['avg_delay_days'] !== null) {
            $delayIndex = min(1.0, $behaviorProfile['avg_delay_days'] / 3.0);
        }
        if ($failureDetection !== null && isset($failureDetection['delay_count_30d'])) {
            $delayCount = $failureDetection['delay_count_30d'];
            $delayIndex = max($delayIndex, min(1.0, $delayCount / 10.0));
        }

        $burnoutIndex = $behaviorProfile ? ($behaviorProfile['skip_rate_30d'] ?? 0.0) : 0.0;
        if ($failureDetection !== null && isset($failureDetection['risk_score'])) {
            $burnoutIndex = max($burnoutIndex, $failureDetection['risk_score']);
        }

        $riskTier = $failureDetection['risk_tier'] ?? 'normal';

        $lastCompletedAt = WorkTaskInstance::whereIn('work_task_id', $taskIds)
            ->where('instance_date', $today)
            ->where('status', WorkTaskInstance::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->max('completed_at');
        $momentumDrop4h = false;
        if ($lastCompletedAt !== null) {
            $last = Carbon::parse($lastCompletedAt, 'Asia/Ho_Chi_Minh');
            $momentumDrop4h = $last->diffInHours(Carbon::now('Asia/Ho_Chi_Minh'), false) >= 4;
        } elseif ($plannedTodayCount > 0) {
            $momentumDrop4h = true;
        }

        $completedAtToday = WorkTaskInstance::whereIn('work_task_id', $taskIds)
            ->where('instance_date', $today)
            ->where('status', WorkTaskInstance::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->pluck('completed_at');
        $momentumStreakToday = 0;
        $prevAt = null;
        foreach ($completedAtToday as $at) {
            $t = Carbon::parse($at, 'Asia/Ho_Chi_Minh');
            if ($prevAt === null) {
                $momentumStreakToday = 1;
                $prevAt = $t;
                continue;
            }
            if ($prevAt->diffInHours($t, false) <= 4) {
                $momentumStreakToday++;
                $prevAt = $t;
            } else {
                break;
            }
        }

        return [
            'execution_rate_7d' => $executionRate7,
            'execution_rate_30d' => $executionRate30,
            'focus_rate_today' => $focusRateToday,
            'delay_index' => round($delayIndex, 4),
            'burnout_index' => round($burnoutIndex, 4),
            'completed_today' => $completedTodayCount,
            'planned_today' => $plannedTodayCount,
            'risk_tier' => $riskTier,
            'momentum_completed_today' => $completedTodayCount,
            'momentum_drop_4h' => $momentumDrop4h,
            'momentum_streak_today' => $momentumStreakToday,
            'last_completed_at_today' => $lastCompletedAt,
        ];
    }

    protected function emptyMetrics(): array
    {
        return [
            'execution_rate_7d' => 0.0,
            'execution_rate_30d' => 0.0,
            'focus_rate_today' => null,
            'delay_index' => 0.0,
            'burnout_index' => 0.0,
            'completed_today' => 0,
            'planned_today' => 0,
            'risk_tier' => 'normal',
            'momentum_completed_today' => 0,
            'momentum_drop_4h' => false,
            'momentum_streak_today' => 0,
            'last_completed_at_today' => null,
        ];
    }
}
