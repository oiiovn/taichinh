<?php

namespace App\Services;

use App\Models\CongViecTask;
use App\Models\PaymentSchedule;
use Carbon\Carbon;

/**
 * Chuyển lịch thanh toán (tài chính) sang task công việc.
 * Dùng khi: user bấm "Đưa vào công việc" hoặc khi lịch được gia hạn (next_due_date advance).
 */
class PaymentScheduleToTaskService
{
    /**
     * Payload để pre-fill form thêm/sửa task (JSON cho frontend).
     *
     * @param  Carbon|null  $overrideDueDate  Nếu có thì dùng làm due_date thay vì schedule->next_due_date
     */
    public function buildTaskPayloadFromSchedule(PaymentSchedule $schedule, ?Carbon $overrideDueDate = null): array
    {
        $due = $overrideDueDate ?? $schedule->next_due_date;
        $dueDate = $due ? Carbon::parse($due)->format('Y-m-d') : null;
        $repeat = $this->mapFrequencyToRepeat($schedule);
        $remind = $this->mapReminderDaysToMinutes($schedule->reminder_days);
        $title = $this->buildTaskTitle($schedule);
        $dueTime = config('payment_schedule.default_task_time', '21:00');

        return [
            'title' => $title,
            'description_html' => $this->buildDescriptionHtml($schedule),
            'due_date' => $dueDate,
            'due_time' => $dueDate ? $dueTime : null,
            'repeat' => $repeat['repeat'],
            'repeat_interval' => $repeat['interval'],
            'repeat_until' => null,
            'priority' => 2,
            'remind_minutes_before' => $remind,
            'kanban_status' => 'backlog',
            'project_id' => null,
            'program_id' => null,
            'label_ids' => [],
            'location' => '',
            'category' => 'maintenance',
            'impact' => 'medium',
            'estimated_duration' => 15,
            'available_after' => null,
            'available_before' => null,
            'from_payment_schedule_id' => $schedule->id,
        ];
    }

    /**
     * Task đã tồn tại cho lịch + kỳ (cùng due_date) thì trả về, không tạo trùng.
     */
    public function findExistingTaskForSchedulePeriod(PaymentSchedule $schedule, ?Carbon $dueDate = null, ?int $userId = null): ?CongViecTask
    {
        $userId = $userId ?? $schedule->user_id;
        $due = $dueDate ?? $schedule->next_due_date;
        if (! $due) {
            return null;
        }
        $dueStr = Carbon::parse($due)->format('Y-m-d');

        return CongViecTask::where('user_id', $userId)
            ->where('meta->payment_schedule_id', $schedule->id)
            ->whereRaw('DATE(due_date) = ?', [$dueStr])
            ->first();
    }

    /**
     * Map schedule_id => [task_id, task_title] cho kỳ hiện tại (next_due_date). Để hiển thị "Đã có task" trên view lịch.
     *
     * @param  \Illuminate\Support\Collection<int, PaymentSchedule>  $schedules
     * @return array<int, array{task_id: int, task_title: string}>
     */
    public function getTaskIdByScheduleIdForCurrentPeriod(int $userId, $schedules): array
    {
        $map = [];
        if ($schedules->isEmpty()) {
            return $map;
        }
        $scheduleIds = $schedules->pluck('id')->all();
        $nextDueBySchedule = [];
        foreach ($schedules as $s) {
            $nextDueBySchedule[$s->id] = $s->next_due_date ? Carbon::parse($s->next_due_date)->format('Y-m-d') : null;
        }
        $tasks = CongViecTask::where('user_id', $userId)->whereNotNull('meta')->get(['id', 'title', 'due_date', 'meta']);
        foreach ($tasks as $task) {
            $sid = (int) ($task->meta['payment_schedule_id'] ?? 0);
            if ($sid && in_array($sid, $scheduleIds, true) && isset($nextDueBySchedule[$sid])) {
                $taskDue = $task->due_date ? Carbon::parse($task->due_date)->format('Y-m-d') : null;
                if ($taskDue === $nextDueBySchedule[$sid]) {
                    $map[$sid] = ['task_id' => $task->id, 'task_title' => $task->title];
                }
            }
        }
        return $map;
    }

    /**
     * Tạo task từ lịch (lưu DB). Nếu đã có task cho cùng lịch + kỳ thì trả về task đó (chống spam/trùng).
     *
     * @param  Carbon|null  $dueDate  Hạn task (mặc định next_due_date của lịch)
     */
    public function createTaskFromSchedule(PaymentSchedule $schedule, ?Carbon $dueDate = null, ?int $userId = null): CongViecTask
    {
        $userId = $userId ?? $schedule->user_id;
        $existing = $this->findExistingTaskForSchedulePeriod($schedule, $dueDate, $userId);
        if ($existing) {
            return $existing;
        }

        $payload = $this->buildTaskPayloadFromSchedule($schedule, $dueDate);

        $task = new CongViecTask();
        $task->user_id = $userId;
        $task->title = $payload['title'];
        $task->description_html = $payload['description_html'];
        $task->due_date = $payload['due_date'] ? Carbon::parse($payload['due_date']) : null;
        $task->due_time = $payload['due_time'];
        $task->repeat = $payload['repeat'];
        $task->repeat_interval = $payload['repeat_interval'];
        $task->repeat_until = $payload['repeat_until'] ? Carbon::parse($payload['repeat_until']) : null;
        $task->priority = $payload['priority'];
        $task->remind_minutes_before = $payload['remind_minutes_before'];
        $task->kanban_status = $payload['kanban_status'];
        $task->project_id = $payload['project_id'];
        $task->program_id = $payload['program_id'];
        $task->category = $payload['category'];
        $task->impact = $payload['impact'];
        $task->estimated_duration = $payload['estimated_duration'];
        $task->meta = ['payment_schedule_id' => $schedule->id];
        $task->save();

        return $task;
    }

    private function buildTaskTitle(PaymentSchedule $schedule): string
    {
        $amount = number_format((float) $schedule->expected_amount, 0, ',', '.');
        return 'Thanh toán: ' . $schedule->name . ' (' . $amount . ' ₫)';
    }

    private function buildDescriptionHtml(PaymentSchedule $schedule): string
    {
        $note = $schedule->internal_note ?? '';
        $from = 'Từ lịch thanh toán: ' . e($schedule->name);
        if ($note !== '') {
            return '<p>' . e($note) . '</p><p class="text-gray-500 text-sm mt-2">' . $from . '</p>';
        }
        return '<p class="text-gray-500 text-sm">' . $from . '</p>';
    }

    /** @return array{repeat: string, interval: int} */
    private function mapFrequencyToRepeat(PaymentSchedule $schedule): array
    {
        switch ($schedule->frequency) {
            case PaymentSchedule::FREQUENCY_MONTHLY:
                return ['repeat' => 'monthly', 'interval' => 1];
            case PaymentSchedule::FREQUENCY_EVERY_2_MONTHS:
                return ['repeat' => 'monthly', 'interval' => 2];
            case PaymentSchedule::FREQUENCY_QUARTERLY:
                return ['repeat' => 'monthly', 'interval' => 3];
            case PaymentSchedule::FREQUENCY_YEARLY:
                return ['repeat' => 'monthly', 'interval' => 12];
            case PaymentSchedule::FREQUENCY_CUSTOM_DAYS:
            default:
                return ['repeat' => 'none', 'interval' => 1];
        }
    }

    private function mapReminderDaysToMinutes(?int $reminderDays): ?int
    {
        if ($reminderDays === null || $reminderDays <= 0) {
            return null;
        }
        $minutes = $reminderDays * 1440;
        $options = [0, 5, 15, 30, 60, 120, 1440];
        $closest = $options[0];
        foreach ($options as $opt) {
            if (abs($minutes - $opt) < abs($minutes - $closest)) {
                $closest = $opt;
            }
        }
        if ($minutes > 1440) {
            $closest = 1440;
        }
        return $closest === 0 ? null : $closest;
    }
}
