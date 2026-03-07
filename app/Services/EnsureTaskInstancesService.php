<?php

namespace App\Services;

use App\Models\CongViecTask;
use App\Models\WorkTaskInstance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lazy generate task instances cho ngày chỉ định.
 * Gọi khi user mở trang Công việc để đảm bảo mọi task "rơi vào" ngày đó đều có instance.
 */
class EnsureTaskInstancesService
{
    /**
     * Đảm bảo tồn tại instance cho mọi task có kỳ vọng làm trong ngày $date.
     */
    public function ensureForUserAndDate(int $userId, string $date): void
    {
        $d = Carbon::parse($date)->format('Y-m-d');
        $tasks = $this->tasksOccurringOnDate($userId, $d);
        $existing = WorkTaskInstance::where('instance_date', $d)
            ->whereIn('work_task_id', $tasks->pluck('id'))
            ->pluck('work_task_id')
            ->all();
        $toCreate = $tasks->pluck('id')->diff($existing)->all();
        if (empty($toCreate)) {
            return;
        }
        $rows = array_map(fn (int $taskId) => [
            'work_task_id' => $taskId,
            'instance_date' => $d,
            'status' => WorkTaskInstance::STATUS_PENDING,
            'created_at' => now(),
            'updated_at' => now(),
        ], $toCreate);
        DB::table('work_task_instances')->insert($rows);
    }

    /**
     * Tasks có kỳ vọng làm trong ngày: due_date + occursOn hoặc program_id (due null hoặc <= date).
     */
    private function tasksOccurringOnDate(int $userId, string $date): \Illuminate\Support\Collection
    {
        $tasksWithDue = CongViecTask::where('user_id', $userId)
            ->whereNotNull('due_date')
            ->where('due_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('repeat_until')->orWhere('repeat_until', '>=', $date);
            })
            ->get()
            ->filter(fn (CongViecTask $t) => $t->occursOn($date));
        $programTasksNoDue = CongViecTask::where('user_id', $userId)
            ->whereNotNull('program_id')
            ->where(function ($q) use ($date) {
                $q->whereNull('due_date')->orWhere('due_date', '<=', $date);
            })
            ->get();
        return $tasksWithDue->merge($programTasksNoDue)->unique('id')->values();
    }
}
