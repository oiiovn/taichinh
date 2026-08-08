<?php

namespace App\Http\Controllers\Food;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\FoodAttendanceSaleDay;
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

        // Ưu tiên ?month=YYYY-MM (mobile). Desktop quản lý vẫn gửi from_date/to_date.
        $useMonthFilter = $request->filled('month')
            || (! $request->filled('from_date') && ! $request->filled('to_date'));

        if ($useMonthFilter) {
            try {
                $monthStart = $request->filled('month')
                    ? Carbon::createFromFormat('Y-m', (string) $request->input('month'))->startOfMonth()
                    : now()->startOfMonth();
            } catch (\Throwable) {
                $monthStart = now()->startOfMonth();
            }
            $from = $monthStart->copy()->startOfDay();
            $to = $monthStart->copy()->endOfMonth();
        } else {
            $from = Carbon::parse($request->input('from_date'))->startOfDay();
            $to = Carbon::parse($request->input('to_date'))->endOfDay();
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
        $saleDays = FoodAttendanceSaleDay::query()
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('work_date')
            ->get();
        $saleDates = $saleDays->map(fn ($d) => $d->work_date->toDateString())->all();
        $saleDateSet = array_fill_keys($saleDates, true);

        return view('pages.food.cham-cong.index', [
            'title' => 'Chấm công',
            'employee' => $employee,
            'logs' => $logs,
            'from' => $from,
            'to' => $to,
            'month' => $from->format('Y-m'),
            'isManager' => $isManager,
            'selectedEmployeeId' => $selectedEmployeeId,
            'currentUserIsEmployee' => (bool) $user->employee,
            'employeesForSelect' => $employeesForSelect,
            'hasCheckedInToday' => $todayLog && $todayLog->check_in_at !== null,
            'hasCheckedOutToday' => $todayLog && $todayLog->check_out_at !== null,
            'hasBreakStartToday' => $todayLog && $todayLog->break_start_at !== null,
            'hasBreakEndToday' => $todayLog && $todayLog->break_end_at !== null,
            'saleDates' => $saleDates,
            'saleDateSet' => $saleDateSet,
            'saleDays' => $saleDays,
        ]);
    }

    public function storeSaleDay(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! $user->canManageFoodChamCong()) {
            abort(403, 'Chỉ quản lý mới được đánh dấu ngày sale.');
        }

        $validated = $request->validate([
            'work_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'month' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'employee_id' => ['nullable', 'integer'],
        ]);

        $workDate = Carbon::parse($validated['work_date'])->startOfDay();
        FoodAttendanceSaleDay::query()->updateOrCreate(
            ['work_date' => $workDate->toDateString()],
            ['note' => filled($validated['note'] ?? null) ? trim((string) $validated['note']) : null]
        );

        return redirect()->route('food.cham-cong', array_filter([
            'month' => $validated['month'] ?? $workDate->format('Y-m'),
            'employee_id' => $validated['employee_id'] ?? null,
        ]))->with('success', 'Đã đánh dấu ngày sale '.$workDate->format('d/m/Y').' (tính công từ giờ vào, kể cả trước 11:30).');
    }

    public function destroySaleDay(Request $request, FoodAttendanceSaleDay $saleDay): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! $user->canManageFoodChamCong()) {
            abort(403, 'Chỉ quản lý mới được bỏ đánh dấu ngày sale.');
        }

        $month = $request->input('month', $saleDay->work_date?->format('Y-m'));
        $workLabel = $saleDay->work_date?->format('d/m/Y') ?? '';
        $saleDay->delete();

        return redirect()->route('food.cham-cong', array_filter([
            'month' => $month,
            'employee_id' => $request->input('employee_id'),
        ]))->with('success', 'Đã bỏ ngày sale'.($workLabel !== '' ? ' '.$workLabel : '').'.');
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
                $log->refresh();
                $employee->applyLatePenaltyNote($log);
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

        $duplicate = AttendanceLog::query()
            ->where('employee_id', $log->employee_id)
            ->whereDate('work_date', $workDate->toDateString())
            ->where('id', '!=', $log->id)
            ->exists();
        if ($duplicate) {
            return redirect()
                ->route('food.cham-cong', array_filter([
                    'employee_id' => $log->employee_id,
                    'month' => $workDate->format('Y-m'),
                ]))
                ->with('error', 'Nhân viên đã có chấm công ngày '.$workDate->format('d/m/Y').'. Không thể đổi sang ngày trùng.');
        }

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
        $log->loadMissing('employee');
        if ($log->employee) {
            $log->employee->applyLatePenaltyNote($log);
        }

        return redirect()->route('food.cham-cong', array_filter([
            'employee_id' => $log->employee_id,
            'month' => $workDate->format('Y-m'),
        ]))->with('success', 'Đã cập nhật chấm công.');
    }

    public function destroy(Request $request, AttendanceLog $log): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! $user->canManageFoodChamCong()) {
            abort(403, 'Chỉ quản lý mới được xóa chấm công.');
        }

        $employeeId = $log->employee_id;
        $month = $log->work_date
            ? Carbon::parse($log->work_date)->format('Y-m')
            : now()->format('Y-m');
        $workLabel = $log->work_date
            ? Carbon::parse($log->work_date)->format('d/m/Y')
            : '';

        $log->delete();

        return redirect()->route('food.cham-cong', array_filter([
            'employee_id' => $employeeId,
            'month' => $month,
        ]))->with('success', 'Đã xóa chấm công'.($workLabel !== '' ? ' ngày '.$workLabel : '').'.');
    }

    public function storeManual(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! $user->canManageFoodChamCong()) {
            abort(403, 'Chỉ quản lý mới được thêm chấm công thủ công.');
        }

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'work_date' => ['required', 'date'],
            'check_in_time' => ['nullable', 'string', 'max:5'],
            'check_out_time' => ['nullable', 'string', 'max:5'],
            'break_start_time' => ['nullable', 'string', 'max:5'],
            'break_end_time' => ['nullable', 'string', 'max:5'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $workDate = Carbon::parse($validated['work_date'])->startOfDay();
        $timePattern = '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/';
        $toDateTime = static function (Carbon $date, ?string $timeText) use ($timePattern) {
            $time = trim((string) $timeText);
            if ($time === '' || ! preg_match($timePattern, $time)) {
                return null;
            }

            return Carbon::parse($date->format('Y-m-d').' '.$time);
        };

        $log = AttendanceLog::query()->firstOrNew([
            'employee_id' => (int) $validated['employee_id'],
            'work_date' => $workDate,
        ]);

        $log->check_in_at = $toDateTime($workDate, $validated['check_in_time'] ?? null);
        $log->check_out_at = $toDateTime($workDate, $validated['check_out_time'] ?? null);
        $log->break_start_at = $toDateTime($workDate, $validated['break_start_time'] ?? null);
        $log->break_end_at = $toDateTime($workDate, $validated['break_end_time'] ?? null);
        $log->note = $validated['note'] ?: null;
        $log->save();

        $employee = Employee::query()->find((int) $validated['employee_id']);
        if ($employee) {
            $employee->applyLatePenaltyNote($log);
        }

        return redirect()->route('food.cham-cong', [
            'employee_id' => (int) $validated['employee_id'],
            'from_date' => $workDate->format('Y-m-d'),
            'to_date' => $workDate->format('Y-m-d'),
        ])->with('success', 'Đã thêm chấm công thủ công.');
    }
}
