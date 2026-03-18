<?php

namespace App\Services\Food;

use App\Models\AttendanceLog;
use App\Models\Employee;
use Carbon\Carbon;

class PayrollService
{
    /**
     * Tính lương cho nhân viên trong khoảng thời gian.
     *
     * @return array{work_days: int, work_minutes: int, leave_days_approved: int, gross_salary: float, salary_type: string, salary_rate: float}
     */
    public function calculateForPeriod(Employee $employee, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $logs = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$from, $to])
            ->whereNotNull('check_in_at')
            ->whereNotNull('check_out_at')
            ->get();

        $workMinutes = $logs->sum(fn ($log) => $log->work_minutes ?? 0);
        $workDays = $logs->count();

        $leaveDaysApproved = (int) $employee->leaveRequests()
            ->where('status', 'approved')
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('from_date', [$from, $to])
                    ->orWhereBetween('to_date', [$from, $to])
                    ->orWhere(fn ($q2) => $q2->where('from_date', '<=', $from)->where('to_date', '>=', $to));
            })
            ->get()
            ->sum(fn ($lr) => $this->countLeaveDaysInRange($lr->from_date, $lr->to_date, $from, $to));

        $rate = (float) $employee->salary_rate;
        $type = $employee->salary_type;
        $grossSalary = 0.0;

        switch ($type) {
            case Employee::SALARY_TYPE_HOUR:
                $grossSalary = ($workMinutes / 60) * $rate;
                break;
            case Employee::SALARY_TYPE_DAY:
                $grossSalary = $workDays * $rate;
                break;
            case Employee::SALARY_TYPE_MONTH:
                $days = $from->copy()->startOfDay()->diffInDays($to->copy()->endOfDay()) + 1;
                $grossSalary = ($days / 30) * $rate;
                break;
        }

        return [
            'work_days' => $workDays,
            'work_minutes' => $workMinutes,
            'leave_days_approved' => $leaveDaysApproved,
            'gross_salary' => round($grossSalary, 2),
            'salary_type' => $type,
            'salary_rate' => $rate,
        ];
    }

    private function countLeaveDaysInRange(Carbon $leaveFrom, Carbon $leaveTo, Carbon $rangeFrom, Carbon $rangeTo): int
    {
        $start = $leaveFrom->max($rangeFrom);
        $end = $leaveTo->min($rangeTo);

        return max(0, $start->diffInDays($end) + 1);
    }
}
