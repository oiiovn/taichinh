<?php

namespace App\Services;

use App\Models\WorkTaskInstance;
use Illuminate\Support\Facades\DB;

/**
 * Task pattern learning: giờ làm tốt nhất theo lịch sử hoàn thành.
 * Sau vài ngày: "check ads" → duration + best hour để gợi ý khi tạo task.
 */
class TaskPatternLearningService
{
    protected const LAST_N_COMPLETIONS = 30;

    /**
     * Giờ (0–23) mà user thường hoàn thành task này nhất; null nếu chưa đủ dữ liệu.
     */
    public function getPreferredHour(int $workTaskId): ?int
    {
        $rows = WorkTaskInstance::where('work_task_id', $workTaskId)
            ->where('status', WorkTaskInstance::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->limit(self::LAST_N_COMPLETIONS)
            ->get(['completed_at']);

        if ($rows->count() < 3) {
            return null;
        }

        $byHour = [];
        foreach ($rows as $r) {
            if (! $r->completed_at) {
                continue;
            }
            $h = (int) $r->completed_at->format('G');
            $byHour[$h] = ($byHour[$h] ?? 0) + 1;
        }
        if (empty($byHour)) {
            return null;
        }

        arsort($byHour);
        return (int) array_key_first($byHour);
    }

    /** Trả về "HH:00" cho gợi ý due_time hoặc null. */
    public function getPreferredTimeString(int $workTaskId): ?string
    {
        $h = $this->getPreferredHour($workTaskId);
        if ($h === null) {
            return null;
        }
        return sprintf('%02d:00', $h);
    }
}
