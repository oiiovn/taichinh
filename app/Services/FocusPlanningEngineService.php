<?php

namespace App\Services;

use App\Models\WorkTaskInstance;
use Illuminate\Support\Collection;

/**
 * Dynamic Today Planning: knapsack-like selection.
 * Input: tasksToday (sorted by priority), available_time_today (phút), estimated_duration per task.
 * Output: Focus tasks (vừa thời gian), Secondary, Backlog.
 */
class FocusPlanningEngineService
{
    /** Thời gian ước tính mặc định (phút) khi task không có estimated_duration */
    protected const DEFAULT_TASK_MINUTES = 30;

    /**
     * @param  array<int, array{score: float}>  $priorityScores  instance_id => ['score' => float]
     * @return array{focus: Collection, secondary: Collection, backlog: Collection, total_planned_minutes: int, available_minutes: int}
     */
    public function plan(
        Collection $tasksTodaySorted,
        array $priorityScores,
        int $availableMinutes,
        int $defaultTaskMinutes = self::DEFAULT_TASK_MINUTES
    ): array {
        $focus = collect();
        $secondary = collect();
        $backlog = collect();
        $timeUsed = 0;

        foreach ($tasksTodaySorted as $instance) {
            $duration = $this->getTaskMinutes($instance, $defaultTaskMinutes);
            if ($timeUsed + $duration <= $availableMinutes) {
                $focus->push($instance);
                $timeUsed += $duration;
            } else {
                $secondary->push($instance);
            }
        }

        return [
            'focus' => $focus,
            'secondary' => $secondary,
            'backlog' => $backlog,
            'total_planned_minutes' => $timeUsed,
            'available_minutes' => $availableMinutes,
        ];
    }

    /** task_duration = avg(last 5 completion durations) ?? estimated_duration ?? default (duration learning). */
    protected function getTaskMinutes(WorkTaskInstance $instance, int $default): int
    {
        $task = $instance->task;
        $predicted = app(TaskDurationLearningService::class)->getPredictedMinutes($task->id);
        if ($predicted !== null) {
            return $predicted;
        }
        $min = $task->estimated_duration ?? $task->actual_duration ?? null;
        if ($min !== null && $min > 0) {
            return (int) $min;
        }
        return $default;
    }

    /** Lấy available_minutes mặc định từ config hoặc 120. */
    public function getDefaultAvailableMinutes(?int $userId = null): int
    {
        $config = config('behavior_intelligence.execution_intelligence.focus_planning.default_available_minutes', 120);
        return (int) $config;
    }
}
