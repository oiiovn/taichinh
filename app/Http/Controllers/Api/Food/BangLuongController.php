<?php

namespace App\Http\Controllers\Api\Food;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeSalaryPayment;
use App\Models\SalaryAdvance;
use App\Services\Food\PayrollService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API bảng lương cá nhân (Sanctum) — khớp Food\PayrollController::myPayroll.
 */
class BangLuongController extends Controller
{
    public function __construct(
        protected PayrollService $payrollService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $employee = $user->employee?->load(['salaryRates', 'user']);
        if (! $employee) {
            return response()->json([
                'ok' => false,
                'message' => 'Bạn chưa được thêm vào danh sách nhân viên.',
            ], 403);
        }
        if (! method_exists($user, 'canUseFoodEmployee') || ! $user->canUseFoodEmployee()) {
            return response()->json([
                'ok' => false,
                'message' => 'Bạn chưa được cấp quyền dùng phần nhân viên.',
            ], 403);
        }

        try {
            $monthStart = $request->filled('month')
                ? Carbon::createFromFormat('Y-m', (string) $request->input('month'))->startOfMonth()
                : now()->startOfMonth();
        } catch (\Throwable) {
            $monthStart = now()->startOfMonth();
        }

        $from = $monthStart->copy()->startOfDay();
        $to = $monthStart->copy()->endOfMonth();
        $month = $from->format('Y-m');

        $payroll = $this->payrollService->calculateForPeriod($employee, $from, $to);

        $payments = EmployeeSalaryPayment::query()
            ->with(['creator:id,name'])
            ->where('employee_id', $employee->id)
            ->whereDate('pay_period_month', $from->toDateString())
            ->orderByDesc('paid_at')
            ->get();

        $totalPaid = (float) $payments->sum('amount');
        $net = (float) ($payroll['net_salary'] ?? $payroll['gross_salary'] ?? 0);
        $remaining = round($net - $totalPaid, 2);

        $advances = SalaryAdvance::query()
            ->with(['approver:id,name'])
            ->where('employee_id', $employee->id)
            ->where('status', SalaryAdvance::STATUS_PAID)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('paid_at', [$from, $to])
                    ->orWhere(function ($q2) use ($from, $to) {
                        $q2->whereNull('paid_at')
                            ->whereBetween('approved_at', [$from, $to]);
                    });
            })
            ->orderByDesc('paid_at')
            ->get();

        $history = [];

        foreach ($payments as $pay) {
            $history[] = [
                'id' => 'pay-'.$pay->id,
                'kind' => 'payment',
                'payment_type' => $pay->payment_type,
                'title' => EmployeeSalaryPayment::paymentTypeLabels()[$pay->payment_type] ?? $pay->payment_type,
                'amount' => (float) $pay->amount,
                'signed_amount' => (float) $pay->amount,
                'payment_method' => $pay->payment_method,
                'payment_method_label' => EmployeeSalaryPayment::paymentMethodLabels()[$pay->payment_method] ?? $pay->payment_method,
                'note' => $pay->note,
                'paid_at' => $pay->paid_at?->toIso8601String(),
                'paid_at_label' => $pay->paid_at?->format('d/m/Y H:i'),
                'status' => 'received',
                'status_label' => 'Đã nhận',
                'recorded_by' => $pay->creator?->name,
                'is_deduction' => false,
            ];
        }

        foreach ($advances as $adv) {
            $at = $adv->paid_at ?? $adv->approved_at;
            $history[] = [
                'id' => 'adv-'.$adv->id,
                'kind' => 'advance',
                'payment_type' => 'advance',
                'title' => 'Ứng lương',
                'amount' => (float) $adv->amount,
                'signed_amount' => -1 * (float) $adv->amount,
                'payment_method' => null,
                'payment_method_label' => null,
                'note' => $adv->reason,
                'paid_at' => $at?->toIso8601String(),
                'paid_at_label' => $at?->format('d/m/Y H:i'),
                'status' => 'deducted',
                'status_label' => 'Đã trừ',
                'recorded_by' => $adv->approver?->name,
                'is_deduction' => true,
            ];
        }

        usort($history, function ($a, $b) {
            return strcmp((string) ($b['paid_at'] ?? ''), (string) ($a['paid_at'] ?? ''));
        });

        $salaryType = $payroll['salary_type'] ?? Employee::SALARY_TYPE_HOUR;
        $salaryRate = (float) ($payroll['salary_rate'] ?? 0);
        $rateSuffix = match ($salaryType) {
            Employee::SALARY_TYPE_DAY => 'đ/ngày',
            Employee::SALARY_TYPE_MONTH => 'đ/tháng',
            default => 'đ/giờ',
        };

        return response()->json([
            'ok' => true,
            'month' => $month,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->user?->name ?? $user->name ?? '—',
            ],
            'overview' => [
                'work_days' => (int) ($payroll['work_days'] ?? 0),
                'work_minutes' => (int) ($payroll['work_minutes'] ?? 0),
                'leave_days_approved' => (int) ($payroll['leave_days_approved'] ?? 0),
                'salary_type' => $salaryType,
                'salary_type_label' => Employee::salaryTypeLabels()[$salaryType] ?? $salaryType,
                'salary_rate' => $salaryRate,
                'salary_rate_label' => $this->formatMoney($salaryRate).' '.$rateSuffix,
                'gross_salary' => (float) ($payroll['gross_salary'] ?? 0),
                'late_penalty' => (float) ($payroll['late_penalty'] ?? 0),
                'late_minutes' => (int) ($payroll['late_minutes'] ?? 0),
                'net_salary' => $net,
                'total_paid' => $totalPaid,
                'remaining' => $remaining,
            ],
            'history' => $history,
            'history_total' => count($history),
        ]);
    }

    private function formatMoney(float $n): string
    {
        return number_format($n, 0, ',', '.');
    }
}
