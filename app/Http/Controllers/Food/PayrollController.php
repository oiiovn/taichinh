<?php

namespace App\Http\Controllers\Food;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeSalaryPayment;
use App\Services\Food\PayrollService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function __construct(
        protected PayrollService $payrollService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        if (! $user || ! $user->canViewFoodPayroll()) {
            abort(403);
        }

        $month = $request->input('month', now()->format('Y-m'));
        $from = Carbon::parse($month.'-01')->startOfDay();
        $to = $from->copy()->endOfMonth();
        $periodDate = $from->toDateString();

        $employees = Employee::with(['user', 'salaryRates'])->where('active', true)->orderBy('id')->get();
        $paymentsByEmployee = EmployeeSalaryPayment::query()
            ->with(['creator'])
            ->whereDate('pay_period_month', $periodDate)
            ->orderByDesc('paid_at')
            ->get()
            ->groupBy('employee_id');

        $rows = [];
        foreach ($employees as $emp) {
            $empPayments = $paymentsByEmployee->get($emp->id, collect());
            $rows[] = [
                'employee' => $emp,
                'payroll' => $this->payrollService->calculateForPeriod($emp, $from, $to),
                'payments' => $empPayments,
                'total_paid' => (float) $empPayments->sum('amount'),
            ];
        }

        return view('pages.food.luong.index', [
            'title' => 'Bảng lương',
            'rows' => $rows,
            'month' => $month,
            'from' => $from,
            'to' => $to,
            'employees' => $employees,
            'canRecordPayment' => $user->canRecordFoodSalaryPayment(),
            'paymentTypes' => EmployeeSalaryPayment::paymentTypeLabels(),
            'paymentMethods' => EmployeeSalaryPayment::paymentMethodLabels(),
        ]);
    }

    public function storePayment(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! $user->canRecordFoodSalaryPayment()) {
            abort(403);
        }

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'month' => ['required', 'date_format:Y-m'],
            'payment_type' => ['required', Rule::in(array_keys(EmployeeSalaryPayment::paymentTypeLabels()))],
            'amount' => ['required', 'numeric', 'min:1000'],
            'payment_method' => ['required', Rule::in(array_keys(EmployeeSalaryPayment::paymentMethodLabels()))],
            'note' => ['nullable', 'string', 'max:1000'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $periodStart = Carbon::parse($validated['month'].'-01')->startOfDay();

        EmployeeSalaryPayment::query()->create([
            'employee_id' => (int) $validated['employee_id'],
            'pay_period_month' => $periodStart->toDateString(),
            'payment_type' => $validated['payment_type'],
            'amount' => (int) round((float) $validated['amount']),
            'payment_method' => $validated['payment_method'],
            'note' => $validated['note'] ?? null,
            'paid_at' => isset($validated['paid_at'])
                ? Carbon::parse($validated['paid_at'])
                : now(),
            'created_by_user_id' => $user->id,
        ]);

        return redirect()
            ->route('food.luong', ['month' => $validated['month']])
            ->with('success', 'Đã ghi nhận thanh toán lương.');
    }

    /** Trang xem lương cá nhân cho nhân viên. */
    public function myPayroll(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        $employee = $user->employee?->load('salaryRates');
        if (! $employee) {
            return redirect()->route('food.cong-no')->with('info', 'Bạn chưa được thêm vào danh sách nhân viên.');
        }
        if (! $user->canUseFoodEmployee()) {
            return redirect()->route('food')->with('error', 'Bạn chưa được cấp quyền dùng phần nhân viên.');
        }

        $month = $request->input('month', now()->format('Y-m'));
        $from = Carbon::parse($month.'-01')->startOfDay();
        $to = $from->copy()->endOfMonth();
        $payroll = $this->payrollService->calculateForPeriod($employee, $from, $to);

        $payments = EmployeeSalaryPayment::query()
            ->with(['creator'])
            ->where('employee_id', $employee->id)
            ->whereDate('pay_period_month', $from->toDateString())
            ->orderByDesc('paid_at')
            ->get();

        $totalPaid = (float) $payments->sum('amount');

        return view('pages.food.luong.my-payroll', [
            'title' => 'Lương của tôi',
            'employee' => $employee,
            'payroll' => $payroll,
            'month' => $month,
            'from' => $from,
            'to' => $to,
            'payments' => $payments,
            'totalPaid' => $totalPaid,
            'paymentTypes' => EmployeeSalaryPayment::paymentTypeLabels(),
            'paymentMethods' => EmployeeSalaryPayment::paymentMethodLabels(),
        ]);
    }
}
