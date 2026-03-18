<?php

namespace App\Http\Controllers\Food;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveRequestController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        $isManager = $user->canManageFoodXinNghi();
        $employee = $user->canUseFoodEmployee() ? $user->employee : null;

        if ($isManager) {
            $requests = LeaveRequest::query()->with(['employee.user'])->orderByDesc('created_at')->get();
        } elseif ($employee) {
            $requests = $employee->leaveRequests;
        } else {
            $requests = collect();
        }

        return view('pages.food.xin-nghi.index', [
            'title' => 'Xin nghỉ',
            'requests' => $requests,
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
            return redirect()->route('food.xin-nghi')->with('error', 'Bạn không phải nhân viên.');
        }
        if (! $user->canUseFoodEmployee()) {
            return redirect()->route('food.xin-nghi')->with('error', 'Bạn chưa được cấp quyền dùng phần nhân viên.');
        }

        $validated = $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        LeaveRequest::create([
            'employee_id' => $employee->id,
            'from_date' => $validated['from_date'],
            'to_date' => $validated['to_date'],
            'reason' => $validated['reason'] ?? null,
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        return redirect()->route('food.xin-nghi')->with('success', 'Đã gửi đơn xin nghỉ.');
    }

    public function approve(Request $request, LeaveRequest $xinNghi): RedirectResponse
    {
        if (! $request->user()?->canManageFoodXinNghi()) {
            abort(403);
        }
        $xinNghi->update([
            'status' => LeaveRequest::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return redirect()->route('food.xin-nghi')->with('success', 'Đã duyệt đơn xin nghỉ.');
    }

    public function reject(Request $request, LeaveRequest $xinNghi): RedirectResponse
    {
        if (! $request->user()?->canManageFoodXinNghi()) {
            abort(403);
        }
        $xinNghi->update([
            'status' => LeaveRequest::STATUS_REJECTED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return redirect()->route('food.xin-nghi')->with('success', 'Đã từ chối đơn xin nghỉ.');
    }
}
