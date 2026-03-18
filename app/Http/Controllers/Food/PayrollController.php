<?php

namespace App\Http\Controllers\Food;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\Food\PayrollService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function __construct(
        protected PayrollService $payrollService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        if (! $user || ! $user->canManageFoodLuong()) {
            abort(403);
        }

        $month = $request->input('month', now()->format('Y-m'));
        $from = Carbon::parse($month . '-01')->startOfDay();
        $to = $from->copy()->endOfMonth();

        $employees = Employee::with('user')->where('active', true)->orderBy('id')->get();
        $rows = [];
        foreach ($employees as $emp) {
            $rows[] = [
                'employee' => $emp,
                'payroll' => $this->payrollService->calculateForPeriod($emp, $from, $to),
            ];
        }

        return view('pages.food.luong.index', [
            'title' => 'Bảng lương',
            'rows' => $rows,
            'month' => $month,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /** Trang xem lương cá nhân cho nhân viên. */
    public function myPayroll(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        $employee = $user->employee;
        if (! $employee) {
            return redirect()->route('food.cong-no')->with('info', 'Bạn chưa được thêm vào danh sách nhân viên.');
        }
        if (! $user->canUseFoodEmployee()) {
            return redirect()->route('food')->with('error', 'Bạn chưa được cấp quyền dùng phần nhân viên.');
        }

        $month = $request->input('month', now()->format('Y-m'));
        $from = Carbon::parse($month . '-01')->startOfDay();
        $to = $from->copy()->endOfMonth();
        $payroll = $this->payrollService->calculateForPeriod($employee, $from, $to);

        return view('pages.food.luong.my-payroll', [
            'title' => 'Lương của tôi',
            'employee' => $employee,
            'payroll' => $payroll,
            'month' => $month,
            'from' => $from,
            'to' => $to,
        ]);
    }
}
