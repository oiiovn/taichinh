<?php

namespace App\Services;

use App\Models\WorkTaskInstance;
use Illuminate\Support\Facades\DB;

/**
 * Duration learning: avg(last 5 completion durations) per task.
 * task_duration_prediction = avg(actual_duration) từ các instance đã hoàn thành.
 */
class TaskDurationLearningService
{
    protected const LAST_N = 5;

    /** Trả về phút dự đoán cho task, null nếu chưa đủ dữ liệu. */
    public function getPredictedMinutes(int $workTaskId): ?int
    {
        $rows = WorkTaskInstance::where('work_task_id', $workTaskId)
            ->where('status', WorkTaskInstance::STATUS_COMPLETED)
            ->whereNotNull('actual_duration')
            ->where('actual_duration', '>', 0)
            ->orderByDesc('completed_at')
            ->limit(self::LAST_N)
            ->pluck('actual_duration');

        if ($rows->count() < 1) {
            return null;
        }

        $sorted = $rows->sort()->values();
        $n = $sorted->count();
        $median = $n % 2 === 1
            ? $sorted->get((int) floor($n / 2))
            : ($sorted->get($n / 2 - 1) + $sorted->get($n / 2)) / 2;
        return max(1, (int) round($median));
    }
}
