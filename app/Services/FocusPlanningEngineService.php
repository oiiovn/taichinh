<?php

namespace App\Services;

use App\Models\WorkTaskInstance;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Dynamic Today Planning: knapsack-like selection.
 * Execution window: task có available_after thì chỉ khả thi khi now >= available_after.
 * Task chưa đến giờ → đưa vào later (theo slot), không xếp vào focus buổi hiện tại.
 *
 * @param  array<int, array{score: float}>  $priorityScores  instance_id => ['score' => float]
 * @return array{focus: Collection, secondary: Collection, backlog: Collection, later: array, total_planned_minutes: int, available_minutes: int}
 */
class FocusPlanningEngineService
{
    /** Thời gian ước tính mặc định (phút) khi task không có estimated_duration */
    protected const DEFAULT_TASK_MINUTES = 30;

    /**
     * @param  array<int, array{score: float}>  $priorityScores  instance_id => ['score' => float]
     * @return array{focus: Collection, secondary: Collection, backlog: Collection, later: array<int, array{slot: string, instances: Collection}>, total_planned_minutes: int, available_minutes: int}
     */
    /**
     * @param  int  $initialFocusLoad  Phút đã tập trung từ sau lần nghỉ (để chèn slot nghỉ ảo).
     */
    public function plan(
        Collection $tasksTodaySorted,
        array $priorityScores,
        int $availableMinutes,
        int $defaultTaskMinutes = self::DEFAULT_TASK_MINUTES,
        ?Carbon $now = null,
        int $initialFocusLoad = 0
    ): array {
        $now = $now ?? Carbon::now('Asia/Ho_Chi_Minh');
        $nowMinutes = $now->hour * 60 + $now->minute;

        $feasibleNow = collect();
        $laterBySlot = [];
        $missedWindow = collect();

        foreach ($tasksTodaySorted as $instance) {
            $task = $instance->task;
            $beforeMin = $this->timeToMinutes($task->available_before);
            if ($beforeMin !== null && $nowMinutes > $beforeMin) {
                $missedWindow->push($instance);
                continue;
            }
            $afterMin = $this->timeToMinutes($task->available_after);
            if ($afterMin !== null && $nowMinutes < $afterMin) {
                $slot = $this->timeToSlotString($task->available_after);
                if (! isset($laterBySlot[$slot])) {
                    $laterBySlot[$slot] = ['slot' => $slot, 'instances' => collect()];
                }
                $laterBySlot[$slot]['instances']->push($instance);
                continue;
            }
            $feasibleNow->push($instance);
        }

        $focus = collect();
        $secondary = collect();
        $backlog = collect();
        $timeUsed = 0;
        $runningFl = max(0, $initialFocusLoad);
        $breakThreshold = (int) config('behavior_intelligence.break_suggestion.threshold_short_minutes', 45);
        $breakMinutes = (int) config('behavior_intelligence.break_suggestion.break_duration_short', 5);

        foreach ($feasibleNow as $instance) {
            $duration = $this->getTaskMinutes($instance, $defaultTaskMinutes);
            $needBreak = $runningFl + $duration > $breakThreshold && $runningFl >= $breakThreshold;
            if ($needBreak && $timeUsed + $breakMinutes + $duration <= $availableMinutes) {
                $focus->push([
                    'is_break' => true,
                    'duration' => $breakMinutes,
                    'label' => '☕ Nghỉ ' . $breakMinutes . ' phút',
                ]);
                $timeUsed += $breakMinutes;
                $runningFl = 0;
            }
            if ($timeUsed + $duration <= $availableMinutes) {
                $focus->push($instance);
                $timeUsed += $duration;
                $runningFl += $duration;
            } else {
                $secondary->push($instance);
            }
        }

        $later = array_values($laterBySlot);
        usort($later, fn ($a, $b) => strcmp($a['slot'], $b['slot']));

        return [
            'focus' => $focus,
            'secondary' => $secondary,
            'backlog' => $backlog,
            'later' => $later,
            'missed_window' => $missedWindow,
            'total_planned_minutes' => $timeUsed,
            'available_minutes' => $availableMinutes,
        ];
    }

    /** Parse time (HH:MM:SS or HH:MM) to minutes since midnight; null if empty. */
    protected function timeToMinutes($time): ?int
    {
        if ($time === null || $time === '') {
            return null;
        }
        $s = is_object($time) ? (string) $time : (string) $time;
        $s = substr(preg_replace('/\s/', '', $s), 0, 5);
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $s, $m)) {
            return null;
        }
        return (int) $m[1] * 60 + (int) $m[2];
    }

    /** Normalize to "HH:MM" for slot key and display. */
    protected function timeToSlotString($time): string
    {
        if ($time === null || $time === '') {
            return '00:00';
        }
        $s = is_object($time) ? (string) $time : $time;
        $s = substr(preg_replace('/\s/', '', $s), 0, 5);
        return preg_match('/^\d{1,2}:\d{2}$/', $s) ? $s : '00:00';
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
