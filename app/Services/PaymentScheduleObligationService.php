<?php

namespace App\Services;

use App\Models\PaymentSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PaymentScheduleObligationService
{
    public const EXECUTION_OVERDUE = 'overdue';
    public const EXECUTION_DUE_SOON = 'due_soon';
    public const EXECUTION_ACTIVE = 'active';
    public const EXECUTION_PAUSED = 'paused';
    public const EXECUTION_ENDED = 'ended';

    /**
     * Nghĩa vụ trong 30 ngày tới (chỉ lịch active).
     */
    public function obligationsNext30Days(int $userId): array
    {
        return $this->obligationsInWindow($userId, 30);
    }

    /**
     * Nghĩa vụ trong 90 ngày tới.
     */
    public function obligationsNext90Days(int $userId): array
    {
        return $this->obligationsInWindow($userId, 90);
    }

    /**
     * Timeline tổng nghĩa vụ theo tháng (cho projection / runway).
     * Key: Y-m, value: tổng VNĐ trong tháng đó.
     */
    public function timelineByMonth(int $userId, int $months = 6): array
    {
        $schedules = PaymentSchedule::where('user_id', $userId)
            ->where('status', PaymentSchedule::STATUS_ACTIVE)
            ->get();
        $start = Carbon::now()->startOfMonth();
        $byMonth = [];
        for ($i = 0; $i < $months; $i++) {
            $key = $start->copy()->addMonths($i)->format('Y-m');
            $byMonth[$key] = 0.0;
        }
        foreach ($schedules as $schedule) {
            $due = $schedule->next_due_date ? Carbon::parse($schedule->next_due_date) : null;
            if (! $due) {
                continue;
            }
            $amount = (float) $schedule->expected_amount;
            $end = $start->copy()->addMonths($months)->endOfMonth();
            $count = 0;
            $maxOccurrences = $months * 2 + 6;
            while ($due && $due->lte($end) && $count < $maxOccurrences) {
                if ($due->gte($start)) {
                    $key = $due->format('Y-m');
                    if (isset($byMonth[$key])) {
                        $byMonth[$key] += $amount;
                    }
                }
                $due = $this->nextOccurrence($schedule, $due);
                $count++;
            }
        }
        return $byMonth;
    }

    /**
     * Trạng thái thực thi để hiển thị: overdue | due_soon | active | paused | ended.
     */
    public function getExecutionStatus(PaymentSchedule $schedule): string
    {
        if ($schedule->status === PaymentSchedule::STATUS_ENDED) {
            return self::EXECUTION_ENDED;
        }
        if ($schedule->status === PaymentSchedule::STATUS_PAUSED) {
            return self::EXECUTION_PAUSED;
        }
        $due = $schedule->next_due_date ? Carbon::parse($schedule->next_due_date)->startOfDay() : null;
        $today = Carbon::now()->startOfDay();
        if (! $due) {
            return self::EXECUTION_ACTIVE;
        }
        if ($due->lt($today)) {
            return self::EXECUTION_OVERDUE;
        }
        if ($due->diffInDays($today, false) >= -7) {
            return self::EXECUTION_DUE_SOON;
        }
        return self::EXECUTION_ACTIVE;
    }

    /**
     * Tổng nghĩa vụ 30 ngày khi loại trừ hoặc giảm một lịch (cho decision layer).
     */
    public function obligations30DaysExcluding(int $userId, ?int $excludeScheduleId = null, ?int $reduceScheduleId = null, float $reducePercent = 0): array
    {
        $base = $this->obligationsInWindow($userId, 30);
        if ($excludeScheduleId === null && $reduceScheduleId === null) {
            return $base;
        }
        $total = (float) $base['total'];
        $items = $base['items'];
        foreach ($items as $i => $item) {
            if (isset($item['schedule_id']) && (int) $item['schedule_id'] === (int) $excludeScheduleId) {
                $total -= (float) $item['amount'];
                unset($items[$i]);
                break;
            }
            if (isset($item['schedule_id']) && (int) $item['schedule_id'] === (int) $reduceScheduleId && $reducePercent > 0) {
                $reduce = (float) $item['amount'] * ($reducePercent / 100);
                $total -= $reduce;
                $items[$i]['amount'] = (float) $item['amount'] - $reduce;
                break;
            }
        }
        return ['total' => round($total, 0), 'items' => array_values($items)];
    }

    private function obligationsInWindow(int $userId, int $days): array
    {
        $schedules = PaymentSchedule::where('user_id', $userId)
            ->where('status', PaymentSchedule::STATUS_ACTIVE)
            ->get();
        $windowEnd = Carbon::now()->addDays($days);
        $windowStart = Carbon::now()->startOfDay();
        $total = 0.0;
        $items = [];
        foreach ($schedules as $schedule) {
            $due = $schedule->next_due_date ? Carbon::parse($schedule->next_due_date)->startOfDay() : null;
            if (! $due) {
                continue;
            }
            $amount = (float) $schedule->expected_amount;
            $maxOccurrences = (int) ceil($days / 7) + 2;
            $count = 0;
            while ($due && $due->lte($windowEnd) && $count < $maxOccurrences) {
                if ($due->gte($windowStart)) {
                    $total += $amount;
                    $items[] = [
                        'schedule_id' => $schedule->id,
                        'name' => $schedule->name,
                        'due_date' => $due->format('Y-m-d'),
                        'amount' => $amount,
                    ];
                }
                $due = $this->nextOccurrence($schedule, $due);
                $count++;
            }
        }
        return ['total' => round($total, 0), 'items' => $items];
    }

    private function nextOccurrence(PaymentSchedule $schedule, Carbon $from): ?Carbon
    {
        $next = $from->copy();
        $dayOfMonth = $schedule->day_of_month ? (int) $schedule->day_of_month : null;
        switch ($schedule->frequency) {
            case PaymentSchedule::FREQUENCY_MONTHLY:
                $next->addMonth();
                if ($dayOfMonth >= 1 && $dayOfMonth <= 31) {
                    $next->day(min($dayOfMonth, $next->daysInMonth));
                }
                return $next;
            case PaymentSchedule::FREQUENCY_EVERY_2_MONTHS:
                $next->addMonths(2);
                if ($dayOfMonth >= 1 && $dayOfMonth <= 31) {
                    $next->day(min($dayOfMonth, $next->daysInMonth));
                }
                return $next;
            case PaymentSchedule::FREQUENCY_QUARTERLY:
                $next->addMonths(3);
                if ($dayOfMonth >= 1 && $dayOfMonth <= 31) {
                    $next->day(min($dayOfMonth, $next->daysInMonth));
                }
                return $next;
            case PaymentSchedule::FREQUENCY_YEARLY:
                $next->addYear();
                if ($dayOfMonth >= 1 && $dayOfMonth <= 31) {
                    $next->day(min($dayOfMonth, $next->daysInMonth));
                }
                return $next;
            case PaymentSchedule::FREQUENCY_CUSTOM_DAYS:
                $interval = (int) $schedule->interval_value;
                if ($interval < 1) {
                    $interval = 30;
                }
                return $next->addDays($interval);
            default:
                return $next->addMonth();
        }
    }
}
