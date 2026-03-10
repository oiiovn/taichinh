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
     * Đảm bảo instance cho mọi ngày trong khoảng [start, end] (Y-m-d).
     * Dùng cho tab Dự kiến: recurring cần có row instance_date > hôm nay.
     */
    /** Số ngày tối đa cho một lần ensure range, tránh loop hàng chục nghìn lần → timeout. */
    private const MAX_RANGE_DAYS = 120;

    public function ensureForUserDateRange(int $userId, string $start, string $end): void
    {
        $from = Carbon::parse($start)->startOfDay();
        $to = Carbon::parse($end)->startOfDay();
        if ($from->gt($to)) {
            return;
        }
        $maxDays = min(
            (int) config('behavior_intelligence.instance_ensure_horizon_days', 90),
            self::MAX_RANGE_DAYS
        );
        $limit = $from->copy()->addDays($maxDays);
        if ($to->gt($limit)) {
            $to = $limit;
        }
        $daysToIterate = (int) $from->diffInDays($to, false) + 1;
        if ($daysToIterate > self::MAX_RANGE_DAYS) {
            $to = $from->copy()->addDays(self::MAX_RANGE_DAYS - 1);
        }
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $this->ensureForUserAndDate($userId, $d->format('Y-m-d'));
        }
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

    /**
     * Đảm bảo instance đúng ngày due_date cho task không lặp / custom có hạn trong tương lai.
     * Tránh lệ thuộc loop theo ngày (task due sau hạn mới vào tasksOccurringOnDate).
     */
    public function ensureOneOffDueDatesAhead(int $userId, string $todayYmd): void
    {
        $today = Carbon::parse($todayYmd)->format('Y-m-d');
        $tasks = CongViecTask::where('user_id', $userId)
            ->where('completed', false)
            ->whereNotNull('due_date')
            ->where('due_date', '>', $today)
            ->where(function ($q) {
                $q->whereNull('repeat')->orWhereIn('repeat', ['none', 'custom']);
            })
            ->get();
        foreach ($tasks as $t) {
            $d = Carbon::parse($t->due_date)->format('Y-m-d');
            $this->ensureForUserAndDate($userId, $d);
        }
    }
}
