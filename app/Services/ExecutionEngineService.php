<?php

namespace App\Services;

use App\Models\WorkTaskInstance;
use Illuminate\Support\Collection;

/**
 * Execution Engine facade: priority → focus → behavior → failure → metrics.
 * Controller / CongViecPageDataService chỉ cần gọi run() để lấy toàn bộ execution data.
 * Dễ test, reuse, code ngắn.
 *
 * @return array{
 *   behavior_profile: array|null,
 *   failure_detection: array|null,
 *   today_priority: array{sorted: Collection, tiers: array, scores: array},
 *   focus_plan: array{focus: Collection, secondary: Collection, backlog: Collection, total_planned_minutes: int, available_minutes: int},
 *   execution_metrics: array|null
 * }
 */
class ExecutionEngineService
{
    public function run(
        ?int $userId,
        string $todayHcm,
        Collection $tasksToday,
        array $taskStreaks,
        ?int $activeProgramId = null
    ): array {
        $behaviorProfile = $userId ? app(BehaviorProfileService::class)->getProfile($userId) : null;
        $failureDetection = $userId ? app(FailureDetectionService::class)->detect($userId) : null;

        $todayPriority = $tasksToday->isNotEmpty()
            ? app(TaskPriorityEngineService::class)->scoreAndTierTodayInstances(
                $tasksToday,
                $taskStreaks,
                $todayHcm,
                $activeProgramId,
                $behaviorProfile
            )
            : ['sorted' => $tasksToday, 'tiers' => ['high' => collect(), 'medium' => collect(), 'low' => collect()], 'scores' => []];

        $focusPlanning = app(FocusPlanningEngineService::class);
        $availableMinutes = $focusPlanning->getDefaultAvailableMinutes($userId);
        $focusPlan = $todayPriority['sorted']->isNotEmpty()
            ? $focusPlanning->plan(
                $todayPriority['sorted'],
                $todayPriority['scores'],
                $availableMinutes,
                (int) config('behavior_intelligence.execution_intelligence.focus_planning.default_task_minutes', 30)
            )
            : ['focus' => collect(), 'secondary' => collect(), 'backlog' => collect(), 'total_planned_minutes' => 0, 'available_minutes' => $availableMinutes];

        $plannedTodayCount = $todayPriority['sorted']->count();
        $completedTodayCount = $userId
            ? WorkTaskInstance::whereHas('task', fn ($q) => $q->where('user_id', $userId))
                ->where('instance_date', $todayHcm)
                ->where('status', WorkTaskInstance::STATUS_COMPLETED)
                ->count()
            : 0;
        $focusPlanFocusIds = $focusPlan['focus']->pluck('id')->all();
        $focusCompletedCount = ! empty($focusPlanFocusIds)
            ? WorkTaskInstance::whereIn('id', $focusPlanFocusIds)->where('status', WorkTaskInstance::STATUS_COMPLETED)->count()
            : 0;

        $executionMetrics = $userId
            ? app(ExecutionMetricsService::class)->getMetrics(
                $userId,
                $behaviorProfile,
                $failureDetection,
                $plannedTodayCount,
                $completedTodayCount,
                $focusPlan['focus']->count(),
                $focusCompletedCount
            )
            : null;

        return [
            'behavior_profile' => $behaviorProfile,
            'failure_detection' => $failureDetection,
            'today_priority' => $todayPriority,
            'focus_plan' => $focusPlan,
            'execution_metrics' => $executionMetrics,
        ];
    }
}
