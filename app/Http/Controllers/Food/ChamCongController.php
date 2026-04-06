<?php

namespace App\Http\Controllers\Food;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChamCongController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        $employee = $user->employee;
        $isManager = $user->canManageFoodChamCong();

        if (! $employee && ! $isManager) {
            return redirect()->route('food')->with('info', 'Bạn chưa được thêm vào danh sách nhân viên.');
        }
        if ($employee && ! $user->canUseFoodEmployee()) {
            return redirect()->route('food')->with('error', 'Bạn chưa được cấp quyền dùng phần nhân viên.');
        }

        $from = $request->input('from_date') ? Carbon::parse($request->from_date)->startOfDay() : now()->startOfMonth();
        $to = $request->input('to_date') ? Carbon::parse($request->to_date)->endOfDay() : now()->endOfDay();

        $employeeId = $request->input('employee_id');
        $selectedEmployeeId = null;
        if ($isManager) {
            if (is_numeric($employeeId) && (int) $employeeId > 0) {
                $selectedEmployeeId = (int) $employeeId;
            }
            $employee = $selectedEmployeeId ? Employee::find($selectedEmployeeId) : null;
        } elseif (! $employee) {
            $employee = $user->employee;
        }

        $logs = collect();
        if ($isManager && ! $selectedEmployeeId) {
            $logs = AttendanceLog::query()
                ->with(['employee.user', 'employee.salaryRates'])
                ->whereBetween('work_date', [$from, $to])
                ->whereHas('employee', fn ($q) => $q->where('active', true))
                ->orderByDesc('work_date')
                ->orderByDesc('id')
                ->get();
        } elseif ($employee) {
            $employee->load('salaryRates');
            $logs = AttendanceLog::query()
                ->with(['employee.user', 'employee.salaryRates'])
                ->where('employee_id', $employee->id)
                ->whereBetween('work_date', [$from, $to])
                ->orderByDesc('work_date')
                ->get();
        }
        $todayLog = null;
        if ($user->employee) {
            $todayLog = AttendanceLog::query()
                ->where('employee_id', $user->employee->id)
                ->whereDate('work_date', now()->toDateString())
                ->first();
        }

        $employeesForSelect = $isManager ? Employee::with('user')->where('active', true)->orderBy('id')->get() : collect();

        return view('pages.food.cham-cong.index', [
            'title' => 'Chấm công',
            'employee' => $employee,
            'logs' => $logs,
            'from' => $from,
            'to' => $to,
            'isManager' => $isManager,
            'selectedEmployeeId' => $selectedEmployeeId,
            'currentUserIsEmployee' => (bool) $user->employee,
            'employeesForSelect' => $employeesForSelect,
            'hasCheckedInToday' => $todayLog && $todayLog->check_in_at !== null,
            'hasCheckedOutToday' => $todayLog && $todayLog->check_out_at !== null,
            'hasBreakStartToday' => $todayLog && $todayLog->break_start_at !== null,
            'hasBreakEndToday' => $todayLog && $todayLog->break_end_at !== null,
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
            return redirect()->route('food.cham-cong')->with('error', 'Bạn không phải nhân viên.');
        }
        if (! $user->canUseFoodEmployee()) {
            return redirect()->route('food.cham-cong')->with('error', 'Bạn chưa được cấp quyền dùng phần nhân viên.');
        }

        $validated = $request->validate([
            'work_date' => ['required', 'date'],
            'action' => ['required', 'string', 'in:check_in,check_out,break_start,break_end'],
        ]);
        $date = Carbon::parse($validated['work_date'])->startOfDay();
        $log = AttendanceLog::query()->firstOrCreate(
            ['employee_id' => $employee->id, 'work_date' => $date],
            ['work_date' => $date]
        );
        $now = now();
        switch ($validated['action']) {
            case 'check_in':
                $log->update(['check_in_at' => $log->check_in_at ?? $now]);
                $msg = 'Đã ghi nhận giờ vào.';
                break;
            case 'check_out':
                $log->update(['check_out_at' => $log->check_out_at ?? $now]);
                $msg = 'Đã ghi nhận giờ ra.';
                break;
            case 'break_start':
                $log->update(['break_start_at' => $log->break_start_at ?? $now]);
                $msg = 'Đã bắt đầu nghỉ.';
                break;
            case 'break_end':
                $log->update(['break_end_at' => $log->break_end_at ?? $now]);
                $msg = 'Đã kết thúc nghỉ.';
                break;
            default:
                $msg = '';
        }

        return redirect()->route('food.cham-cong', [
            'from_date' => $date->format('Y-m-d'),
            'to_date' => $date->format('Y-m-d'),
        ])->with('success', $msg);
    }

    public function update(Request $request, AttendanceLog $log): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! $user->canManageFoodChamCong()) {
            abort(403, 'Chỉ quản lý mới được sửa chấm công.');
        }

        $validated = $request->validate([
            'work_date' => ['required', 'date'],
            'check_in_time' => ['nullable', 'string', 'max:5'],
            'check_out_time' => ['nullable', 'string', 'max:5'],
            'break_start_time' => ['nullable', 'string', 'max:5'],
            'break_end_time' => ['nullable', 'string', 'max:5'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $workDate = Carbon::parse($validated['work_date'])->startOfDay();
        $timePattern = '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/';

        $log->work_date = $workDate;
        $t = trim($validated['check_in_time'] ?? '');
        $log->check_in_at = ($t !== '' && preg_match($timePattern, $t)) ? Carbon::parse($workDate->format('Y-m-d').' '.$t) : null;
        $t = trim($validated['check_out_time'] ?? '');
        $log->check_out_at = ($t !== '' && preg_match($timePattern, $t)) ? Carbon::parse($workDate->format('Y-m-d').' '.$t) : null;
        $t = trim($validated['break_start_time'] ?? '');
        $log->break_start_at = ($t !== '' && preg_match($timePattern, $t)) ? Carbon::parse($workDate->format('Y-m-d').' '.$t) : null;
        $t = trim($validated['break_end_time'] ?? '');
        $log->break_end_at = ($t !== '' && preg_match($timePattern, $t)) ? Carbon::parse($workDate->format('Y-m-d').' '.$t) : null;
        $log->note = $validated['note'] ?: null;
        $log->save();

        $employeeId = $log->employee_id;
        $from = $workDate->format('Y-m-d');
        $to = $workDate->format('Y-m-d');

        return redirect()->route('food.cham-cong', [
            'employee_id' => $employeeId,
            'from_date' => $from,
            'to_date' => $to,
        ])->with('success', 'Đã cập nhật chấm công.');
    }
}
