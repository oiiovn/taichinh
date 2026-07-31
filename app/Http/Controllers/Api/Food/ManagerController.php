<?php

namespace App\Http\Controllers\Api\Food;

use App\Exceptions\Food\AttendanceException;
use App\Http\Requests\Api\Food\FoodManagerAttendanceRequest;
use App\Http\Resources\Api\Food\FoodBranchResource;
use App\Http\Resources\Api\Food\ManagerAttendanceResource;
use App\Http\Resources\Api\Food\ManagerEmployeeResource;
use App\Models\AttendanceLog;
use App\Services\Food\FoodManagerAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManagerController extends FoodApiController
{
    public function __construct(
        private readonly FoodManagerAccessService $access,
    ) {}

    public function branches(Request $request): JsonResponse
    {
        $branches = $this->access->managedBranches($request->user());

        return $this->success(
            FoodBranchResource::collection($branches)->resolve()
        );
    }

    public function employees(FoodManagerAttendanceRequest $request): JsonResponse
    {
        $branchId = $request->filled('branch_id') ? (int) $request->input('branch_id') : null;
        try {
            if ($branchId) {
                $this->access->assertManagedBranch($request->user(), $branchId);
            }
        } catch (AttendanceException $e) {
            return $this->failure($e->getMessage(), $e->errorCode, $e->httpStatus);
        }

        $employees = $this->access->managedEmployeesQuery($request->user(), $branchId)->get();

        return $this->success(
            ManagerEmployeeResource::collection($employees)->resolve()
        );
    }

    public function attendanceToday(FoodManagerAttendanceRequest $request): JsonResponse
    {
        $user = $request->user();
        $branchId = $request->filled('branch_id') ? (int) $request->input('branch_id') : null;
        $date = $request->workDate();

        try {
            $branchIds = $this->access->managedBranchIds($user, $branchId);
        } catch (AttendanceException $e) {
            return $this->failure($e->getMessage(), $e->errorCode, $e->httpStatus);
        }

        if ($branchIds === []) {
            return $this->success([
                'date' => $date->toDateString(),
                'items' => [],
                'summary' => ['total' => 0, 'checked_in' => 0, 'checked_out' => 0, 'absent' => 0],
            ]);
        }

        $employees = $this->access->managedEmployeesQuery($user, $branchId)->get();
        $employeeIds = $employees->pluck('id')->all();

        $logs = AttendanceLog::query()
            ->with(['employee.user', 'foodBranch'])
            ->whereIn('employee_id', $employeeIds ?: [0])
            ->whereDate('work_date', $date->toDateString())
            ->when($branchId, fn ($q) => $q->where('food_branch_id', $branchId))
            ->get()
            ->keyBy('employee_id');

        $items = [];
        $checkedIn = 0;
        $checkedOut = 0;
        $absent = 0;

        foreach ($employees as $employee) {
            $log = $logs->get($employee->id);
            if (! $log) {
                $absent++;
                $items[] = [
                    'employee' => (new ManagerEmployeeResource($employee))->resolve(),
                    'attendance' => null,
                    'status' => 'absent',
                ];
                continue;
            }

            if ($log->check_out_at) {
                $checkedOut++;
                $status = 'checked_out';
            } elseif ($log->check_in_at) {
                $checkedIn++;
                $status = 'checked_in';
            } else {
                $absent++;
                $status = 'absent';
            }

            $items[] = [
                'employee' => (new ManagerEmployeeResource($employee))->resolve(),
                'attendance' => (new ManagerAttendanceResource($log))->resolve(),
                'status' => $status,
            ];
        }

        return $this->success([
            'date' => $date->toDateString(),
            'branch_id' => $branchId,
            'items' => $items,
            'summary' => [
                'total' => count($items),
                'checked_in' => $checkedIn,
                'checked_out' => $checkedOut,
                'absent' => $absent,
            ],
        ]);
    }

    public function attendanceHistory(FoodManagerAttendanceRequest $request): JsonResponse
    {
        $user = $request->user();
        $branchId = $request->filled('branch_id') ? (int) $request->input('branch_id') : null;
        $employeeId = $request->filled('employee_id') ? (int) $request->input('employee_id') : null;
        $from = $request->fromDate();
        $to = $request->toDate();

        try {
            $branchIds = $this->access->managedBranchIds($user, $branchId);
        } catch (AttendanceException $e) {
            return $this->failure($e->getMessage(), $e->errorCode, $e->httpStatus);
        }

        $managedEmployeeIds = $this->access->managedEmployeesQuery($user, $branchId)->pluck('id')->all();
        if ($employeeId !== null && ! in_array($employeeId, $managedEmployeeIds, true)) {
            return $this->failure('Nhân viên không thuộc phạm vi quản lý.', 'EMPLOYEE_NOT_FOUND', 404);
        }

        $query = AttendanceLog::query()
            ->with(['employee.user', 'foodBranch'])
            ->whereIn('employee_id', $managedEmployeeIds ?: [0])
            ->whereDate('work_date', '>=', $from->toDateString())
            ->whereDate('work_date', '<=', $to->toDateString())
            ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
            ->when($branchId, fn ($q) => $q->where('food_branch_id', $branchId))
            ->orderByDesc('work_date')
            ->orderBy('employee_id');

        $paginator = $query->paginate($request->perPage());

        return $this->success([
            'from' => $from->toDateString(),
            'to' => $to->copy()->startOfDay()->toDateString(),
            'items' => ManagerAttendanceResource::collection($paginator->getCollection())->resolve(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
