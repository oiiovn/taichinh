<?php

namespace App\Services\Food;

use App\Models\AttendanceLog;
use App\Models\Employee;
use Carbon\Carbon;

class PayrollService
{
    /**
     * Tính lương cho nhân viên trong khoảng thời gian.
     * Mức lương theo từng ngày lấy từ lịch sử (employee_salary_rates); nếu không có thì dùng mức trên employees.
     *
     * @return array{
     *   work_days: int,
     *   work_minutes: int,
     *   leave_days_approved: int,
     *   gross_salary: float,
     *   late_penalty: float,
     *   net_salary: float,
     *   late_minutes: int,
     *   salary_type: string,
     *   salary_rate: float
     * }
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
            ->orderBy('work_date')
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

        $grossSalary = 0.0;
        $latePenalty = 0.0;
        $lateMinutesTotal = 0;

        $cursor = $from->copy()->startOfDay();
        $endDay = $to->copy()->startOfDay();
        while ($cursor->lte($endDay)) {
            $r = $employee->applicableRateForDate($cursor);
            if ($r['type'] === Employee::SALARY_TYPE_MONTH) {
                $grossSalary += $r['rate'] / 30;
            }
            $cursor->addDay();
        }

        foreach ($logs as $log) {
            $wd = Carbon::parse($log->work_date)->startOfDay();
            $r = $employee->applicableRateForDate($wd);
            if ($r['type'] === Employee::SALARY_TYPE_HOUR) {
                $mins = $log->work_minutes ?? 0;
                $grossSalary += ($mins / 60) * $r['rate'];
            } elseif ($r['type'] === Employee::SALARY_TYPE_DAY) {
                $grossSalary += $r['rate'];
            }

            $lateMins = $employee->lateMinutesForCheckIn($log->check_in_at);
            $lateMinutesTotal += $lateMins;
            $latePenalty += $employee->latePenaltyForMinutes($lateMins);
        }

        $grossSalary = round($grossSalary, 2);
        $latePenalty = round($latePenalty, 2);
        $firstRate = $employee->applicableRateForDate($from);

        return [
            'work_days' => $workDays,
            'work_minutes' => $workMinutes,
            'leave_days_approved' => $leaveDaysApproved,
            'gross_salary' => $grossSalary,
            'late_penalty' => $latePenalty,
            'late_minutes' => $lateMinutesTotal,
            'net_salary' => round(max(0, $grossSalary - $latePenalty), 2),
            'salary_type' => $firstRate['type'],
            'salary_rate' => $firstRate['rate'],
        ];
    }

    private function countLeaveDaysInRange(Carbon $leaveFrom, Carbon $leaveTo, Carbon $rangeFrom, Carbon $rangeTo): int
    {
        $start = $leaveFrom->max($rangeFrom);
        $end = $leaveTo->min($rangeTo);

        return max(0, $start->diffInDays($end) + 1);
    }
}
