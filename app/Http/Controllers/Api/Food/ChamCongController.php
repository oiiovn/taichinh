<?php

namespace App\Http\Controllers\Api\Food;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * API chấm công cho app mobile (Sanctum).
 * Khớp logic web: Food\ChamCongController.
 */
class ChamCongController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $ownEmployee = $user->employee;
        // Chỉ admin / flag can_manage_food_cham_cong mới được lọc nhân viên khác.
        $isManager = method_exists($user, 'canManageFoodChamCong') && $user->canManageFoodChamCong();
        $canUseEmployee = method_exists($user, 'canUseFoodEmployee') && $user->canUseFoodEmployee();

        if (! $isManager && ! $ownEmployee) {
            return response()->json([
                'ok' => false,
                'message' => 'Bạn chưa được thêm vào danh sách nhân viên.',
                'role' => 'none',
            ], 403);
        }
        if (! $isManager && $ownEmployee && ! $canUseEmployee) {
            return response()->json([
                'ok' => false,
                'message' => 'Bạn chưa được cấp quyền dùng phần nhân viên.',
                'role' => 'none',
            ], 403);
        }

        // Nhân viên thường: bỏ qua mọi employee_id client gửi lên.
        $selectedEmployeeId = null;
        $employee = $ownEmployee;
        if ($isManager) {
            $employeeId = $request->input('employee_id');
            if (is_numeric($employeeId) && (int) $employeeId > 0) {
                $selectedEmployeeId = (int) $employeeId;
                $employee = Employee::with('user')->find($selectedEmployeeId);
            } else {
                $employee = null; // manager xem tất cả khi không chọn NV
            }
        }

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
        if ($isManager && $selectedEmployeeId === null) {
            $logs = AttendanceLog::query()
                ->with(['employee.user', 'employee.salaryRates'])
                ->whereBetween('work_date', [$from, $to])
                ->whereHas('employee', fn ($q) => $q->where('active', true))
                ->orderByDesc('work_date')
                ->orderByDesc('id')
                ->get();
        } elseif ($employee) {
            $employee->loadMissing(['user', 'salaryRates']);
            $logs = AttendanceLog::query()
                ->with(['employee.user', 'employee.salaryRates'])
                ->where('employee_id', $employee->id)
                ->whereBetween('work_date', [$from, $to])
                ->orderByDesc('work_date')
                ->get();
        }

        $todayLog = null;
        if ($ownEmployee) {
            $todayLog = AttendanceLog::query()
                ->where('employee_id', $ownEmployee->id)
                ->whereDate('work_date', now()->toDateString())
                ->first();
        }

        // Chỉ manager mới nhận danh sách NV để lọc.
        $employeesForSelect = $isManager
            ? Employee::with('user')->where('active', true)->orderBy('id')->get()
            : collect();

        $role = $isManager ? 'manager' : 'employee';

        return response()->json([
            'ok' => true,
            'role' => $role,
            'month' => $from->format('Y-m'),
            'month_label' => 'tháng '.$from->format('n').' năm '.$from->format('Y'),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'is_manager' => $isManager,
            'can_filter_employees' => $isManager,
            // Khớp web: có hồ sơ nhân viên.
            'current_user_is_employee' => (bool) $ownEmployee,
            // Punch chỉ khi được cấp can_use_food_employee (giống store trên web).
            'can_punch' => (bool) $ownEmployee && $canUseEmployee,
            'selected_employee_id' => $isManager ? $selectedEmployeeId : null,
            'employee' => $employee ? [
                'id' => $employee->id,
                'name' => $employee->user->name ?? ('NV #'.$employee->id),
            ] : ($ownEmployee && ! $isManager ? [
                'id' => $ownEmployee->id,
                'name' => $ownEmployee->user->name ?? $user->name,
            ] : null),
            'employees' => $isManager
                ? $employeesForSelect->map(fn (Employee $e) => [
                    'id' => $e->id,
                    'name' => $e->user->name ?? ('NV #'.$e->id),
                ])->values()
                : [],
            'today' => [
                'has_checked_in' => $todayLog && $todayLog->check_in_at !== null,
                'has_checked_out' => $todayLog && $todayLog->check_out_at !== null,
                'has_break_start' => $todayLog && $todayLog->break_start_at !== null,
                'has_break_end' => $todayLog && $todayLog->break_end_at !== null,
                'log' => $todayLog ? $this->serializeLog($todayLog, $ownEmployee, $isManager) : null,
            ],
            'logs' => $logs->map(function (AttendanceLog $log) use ($employee, $ownEmployee, $isManager) {
                $emp = $log->employee ?? $employee ?? $ownEmployee;

                return $this->serializeLog($log, $emp, $isManager);
            })->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $employee = $user->employee;
        if (! $employee) {
            return response()->json(['ok' => false, 'message' => 'Bạn không phải nhân viên.'], 403);
        }
        if (method_exists($user, 'canUseFoodEmployee') && ! $user->canUseFoodEmployee()) {
            return response()->json(['ok' => false, 'message' => 'Bạn chưa được cấp quyền dùng phần nhân viên.'], 403);
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
        $msg = '';
        switch ($validated['action']) {
            case 'check_in':
                if ($log->check_in_at) {
                    throw ValidationException::withMessages([
                        'action' => ['Hôm nay bạn đã vào ca.'],
                    ]);
                }
                $log->update(['check_in_at' => $now]);
                $log->refresh();
                $employee->applyLatePenaltyNote($log);
                $msg = 'Đã ghi nhận giờ vào.';
                break;
            case 'check_out':
                if ($log->check_out_at) {
                    throw ValidationException::withMessages([
                        'action' => ['Hôm nay bạn đã ra ca.'],
                    ]);
                }
                $log->update(['check_out_at' => $log->check_out_at ?? $now]);
                $msg = 'Đã ghi nhận giờ ra.';
                break;
            case 'break_start':
                if ($log->break_start_at) {
                    throw ValidationException::withMessages([
                        'action' => ['Đã bắt đầu nghỉ rồi.'],
                    ]);
                }
                $log->update(['break_start_at' => $now]);
                $msg = 'Đã bắt đầu nghỉ.';
                break;
            case 'break_end':
                if ($log->break_end_at) {
                    throw ValidationException::withMessages([
                        'action' => ['Đã kết thúc nghỉ rồi.'],
                    ]);
                }
                $log->update(['break_end_at' => $now]);
                $msg = 'Đã kết thúc nghỉ.';
                break;
        }

        $log->refresh();
        $log->loadMissing(['employee.user', 'employee.salaryRates']);

        return response()->json([
            'ok' => true,
            'message' => $msg,
            'log' => $this->serializeLog($log, $employee, false),
            'today' => [
                'has_checked_in' => $log->check_in_at !== null,
                'has_checked_out' => $log->check_out_at !== null,
                'has_break_start' => $log->break_start_at !== null,
                'has_break_end' => $log->break_end_at !== null,
                'log' => $this->serializeLog($log, $employee, false),
            ],
        ]);
    }

    private function serializeLog(AttendanceLog $log, ?Employee $emp, bool $isManager = false): array
    {
        $emp = $emp ?? $log->employee;
        $lateMinutes = 0;
        $latePenalty = 0;
        $dailySalary = null;
        $note = trim((string) ($log->note ?? ''));

        if ($emp) {
            $emp->loadMissing('salaryRates');
            if (method_exists($emp, 'usesLatePenalty') && $emp->usesLatePenalty()) {
                $lateMinutes = (int) $emp->lateMinutesForCheckIn($log->check_in_at, $log->work_date);
                $latePenalty = (float) $emp->latePenaltyForMinutes($lateMinutes);
            }
            if (method_exists($emp, 'stripLatePenaltyNote')) {
                $note = trim($emp->stripLatePenaltyNote($note));
            }
            $dailySalary = $this->dailySalary($log, $emp);
        }

        $workDate = $log->work_date ? Carbon::parse($log->work_date) : null;
        $thu = $workDate
            ? (['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'][$workDate->dayOfWeek] ?? '')
            : '';

        $checkIn = $log->check_in_at?->format('H:i');
        $checkOut = $log->check_out_at?->format('H:i');
        $breakStart = $log->break_start_at?->format('H:i');
        $breakEnd = $log->break_end_at?->format('H:i');
        $isOff = ! $log->check_in_at && ! $log->check_out_at;
        $workMinutes = $log->work_minutes;

        $breakLabel = null;
        if ($breakStart || $breakEnd) {
            $breakLabel = ($breakStart ?? '—').' – '.($breakEnd ?? '—');
        }

        return [
            'id' => $log->id,
            // Luôn trả employee_id của bản ghi; tên NV chỉ hiện với quản lý (giống web).
            'employee_id' => $log->employee_id,
            'employee_name' => $isManager ? ($log->employee?->user?->name) : null,
            'work_date' => $workDate?->toDateString(),
            'work_date_label' => $workDate
                ? $workDate->format('d/m/Y').($thu !== '' ? ' ('.$thu.')' : '')
                : null,
            'day_name' => $thu !== '' ? $thu : null,
            'check_in_at' => $checkIn,
            'check_out_at' => $checkOut,
            'break_start_at' => $breakStart,
            'break_end_at' => $breakEnd,
            'break_label' => $breakLabel,
            'work_minutes' => $workMinutes,
            'status_badge' => $isOff
                ? 'OFF'
                : ($workMinutes !== null ? $workMinutes.' phút' : null),
            'note' => $note !== '' ? $note : null,
            'is_off' => $isOff,
            'late_minutes' => $lateMinutes,
            'late_penalty' => $latePenalty,
            'daily_salary' => $dailySalary,
            'daily_salary_formatted' => $dailySalary !== null
                ? number_format((float) $dailySalary, 0, ',', '.').' đ'
                : null,
        ];
    }

    private function dailySalary(AttendanceLog $log, Employee $emp): ?float
    {
        if (! method_exists($emp, 'applicableRateForDate')) {
            return null;
        }

        $d = Carbon::parse($log->work_date)->startOfDay();
        $ar = $emp->applicableRateForDate($d);
        $r = (float) ($ar['rate'] ?? 0);
        $t = $ar['type'] ?? Employee::SALARY_TYPE_HOUR;
        $gross = null;

        if ($t === Employee::SALARY_TYPE_HOUR) {
            $mins = $log->work_minutes ?? null;
            $gross = $mins !== null ? ($mins / 60) * $r : null;
        } elseif ($t === Employee::SALARY_TYPE_DAY) {
            $gross = $log->check_in_at && $log->check_out_at ? $r : null;
        } elseif ($t === Employee::SALARY_TYPE_MONTH) {
            $gross = $log->check_in_at && $log->check_out_at ? $r / 30 : null;
        }

        if ($gross === null) {
            return null;
        }

        $penalty = 0.0;
        if (method_exists($emp, 'usesLatePenalty') && $emp->usesLatePenalty()) {
            $lateMins = $emp->lateMinutesForCheckIn($log->check_in_at, $log->work_date);
            $penalty = (float) $emp->latePenaltyForMinutes($lateMins);
        }

        return max(0, $gross - $penalty);
    }
}
