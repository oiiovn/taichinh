<?php

namespace App\Http\Controllers\Food;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SalaryAdvance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalaryAdvanceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        $isManager = $user->canManageFoodUngLuong();
        $employee = $user->canUseFoodEmployee() ? $user->employee : null;

        if ($isManager) {
            $advances = SalaryAdvance::query()->with(['employee.user'])->orderByDesc('created_at')->get();
        } elseif ($employee) {
            $advances = $employee->salaryAdvances;
        } else {
            $advances = collect();
        }

        return view('pages.food.ung-luong.index', [
            'title' => 'Ứng lương',
            'advances' => $advances,
            'isManager' => $isManager,
            'employee' => $employee,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        $employee = $user->employee;
        if (! $employee) {
            return redirect()->route('food.ung-luong')->with('error', 'Bạn không phải nhân viên.');
        }
        if (! $user->canUseFoodEmployee()) {
            return redirect()->route('food.ung-luong')->with('error', 'Bạn chưa được cấp quyền dùng phần nhân viên.');
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1000'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        SalaryAdvance::create([
            'employee_id' => $employee->id,
            'amount' => $validated['amount'],
            'reason' => $validated['reason'] ?? null,
            'status' => SalaryAdvance::STATUS_PENDING,
        ]);

        return redirect()->route('food.ung-luong')->with('success', 'Đã gửi đơn ứng lương.');
    }

    public function approve(Request $request, SalaryAdvance $ungLuong): RedirectResponse
    {
        if (! $request->user()?->canManageFoodUngLuong()) {
            abort(403);
        }
        if ($ungLuong->status !== SalaryAdvance::STATUS_PENDING) {
            return redirect()->route('food.ung-luong')->with('error', 'Đơn đã xử lý.');
        }
        $ungLuong->update([
            'status' => SalaryAdvance::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return redirect()->route('food.ung-luong')->with('success', 'Đã duyệt ứng lương.');
    }

    public function reject(Request $request, SalaryAdvance $ungLuong): RedirectResponse
    {
        if (! $request->user()?->canManageFoodUngLuong()) {
            abort(403);
        }
        if ($ungLuong->status !== SalaryAdvance::STATUS_PENDING) {
            return redirect()->route('food.ung-luong')->with('error', 'Đơn đã xử lý.');
        }
        $ungLuong->update([
            'status' => SalaryAdvance::STATUS_REJECTED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return redirect()->route('food.ung-luong')->with('success', 'Đã từ chối ứng lương.');
    }

    public function markPaid(Request $request, SalaryAdvance $ungLuong): RedirectResponse
    {
        if (! $request->user()?->canManageFoodUngLuong()) {
            abort(403);
        }
        if ($ungLuong->status !== SalaryAdvance::STATUS_APPROVED) {
            return redirect()->route('food.ung-luong')->with('error', 'Chỉ đánh dấu đã thanh toán cho đơn đã duyệt.');
        }
        $ungLuong->update([
            'status' => SalaryAdvance::STATUS_PAID,
            'paid_at' => now(),
        ]);

        return redirect()->route('food.ung-luong')->with('success', 'Đã đánh dấu đã thanh toán.');
    }
}
