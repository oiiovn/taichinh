<?php

namespace App\Services;

use App\Models\CongViecTask;
use App\Models\WorkTaskInstance;
use Carbon\Carbon;

/**
 * Streak engine: đếm chuỗi instance hoàn thành liên tiếp (tính từ ngày gần nhất lùi về quá khứ).
 */
class TaskStreakService
{
    /**
     * Streak hiện tại của task: số ngày liên tiếp đã hoàn thành tính từ $upToDate lùi về.
     * Chỉ tính các ngày mà task "rơi vào" (occursOn) và có instance completed.
     *
     * @return array{streak: int, up_to_date: string, task_id: int}
     */
    public function getStreakForTask(int $userId, int $workTaskId, ?string $upToDate = null): array
    {
        $task = CongViecTask::where('id', $workTaskId)->where('user_id', $userId)->first();
        if (! $task || ! $task->due_date) {
            $defaultDate = $upToDate ?? Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d');
            return ['streak' => 0, 'up_to_date' => $defaultDate, 'task_id' => $workTaskId];
        }

        $upTo = $upToDate ? Carbon::parse($upToDate)->startOfDay() : Carbon::now('Asia/Ho_Chi_Minh')->startOfDay();
        $streak = 0;
        $current = $upTo->copy();

        while (true) {
            $d = $current->format('Y-m-d');
            if (! $task->occursOn($d)) {
                $current->subDay();
                continue;
            }
            $instance = WorkTaskInstance::where('work_task_id', $workTaskId)
                ->where('instance_date', $d)
                ->where('status', WorkTaskInstance::STATUS_COMPLETED)
                ->first();
            if (! $instance) {
                break;
            }
            $streak++;
            $current->subDay();
        }

        return [
            'streak' => $streak,
            'up_to_date' => $upTo->format('Y-m-d'),
            'task_id' => $workTaskId,
        ];
    }

    /**
     * Completion rate của task trong khoảng ngày: số instance completed / số ngày task occurs.
     *
     * @return array{completed: int, total_occurrences: int, rate: float}
     */
    public function getCompletionRateInRange(int $userId, int $workTaskId, string $from, string $to): array
    {
        $task = CongViecTask::where('id', $workTaskId)->where('user_id', $userId)->first();
        if (! $task) {
            return ['completed' => 0, 'total_occurrences' => 0, 'rate' => 0.0];
        }

        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();
        $totalOccurrences = 0;
        $completed = 0;

        $current = $start->copy();
        while ($current->lte($end)) {
            $d = $current->format('Y-m-d');
            if ($task->occursOn($d)) {
                $totalOccurrences++;
                $inst = WorkTaskInstance::where('work_task_id', $workTaskId)->where('instance_date', $d)->first();
                if ($inst && $inst->status === WorkTaskInstance::STATUS_COMPLETED) {
                    $completed++;
                }
            }
            $current->addDay();
        }

        $rate = $totalOccurrences > 0 ? round($completed / $totalOccurrences, 4) : 0.0;

        return [
            'completed' => $completed,
            'total_occurrences' => $totalOccurrences,
            'rate' => $rate,
        ];
    }
}
