<?php

namespace App\Services;

use App\Models\CongViecTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HabitInternalizationService
{
    protected int $minStableDays;

    public function __construct()
    {
        $cfg = config('behavior_intelligence.internalization', []);
        $this->minStableDays = (int) ($cfg['min_completions_before_internalized'] ?? 14);
    }

    /**
     * Đánh dấu task/habit là internalized nếu: repeat, completed, ổn định lâu (updated_at đủ cũ), hoàn thành trước/đúng hạn.
     */
    public function detectAndMark(int $userId, string $upToDate): int
    {
        if (! config('behavior_intelligence.layers.habit_internalization', true)) {
            return 0;
        }

        $upTo = Carbon::parse($upToDate);
        $minUpdated = $upTo->copy()->subDays($this->minStableDays);
        $tasks = CongViecTask::where('user_id', $userId)
            ->whereNotNull('repeat')
            ->where('repeat', '!=', 'none')
            ->where('completed', true)
            ->whereNull('internalized_at')
            ->where('updated_at', '<=', $minUpdated)
            ->get();

        $marked = 0;
        foreach ($tasks as $task) {
            if ($this->wasOnTime($task)) {
                $task->internalized_at = $upTo;
                $task->save();
                $marked++;
            }
        }

        return $marked;
    }

    protected function wasOnTime(CongViecTask $task): bool
    {
        if (! $task->due_date) {
            return true;
        }
        $due = Carbon::parse($task->due_date);
        if ($task->due_time) {
            $due->setTimeFromTimeString($task->due_time);
        }

        return $task->updated_at && $task->updated_at->lte($due->copy()->endOfDay());
    }

    /**
     * Kiểm tra task đã internalized thì giảm nhắc (policy dùng).
     */
    public function shouldReduceReminders(CongViecTask $task): bool
    {
        return $task->internalized_at !== null;
    }
}
