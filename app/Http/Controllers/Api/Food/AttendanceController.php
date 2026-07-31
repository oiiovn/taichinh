<?php

namespace App\Http\Controllers\Api\Food;

use App\Exceptions\Food\AttendanceException;
use App\Http\Requests\Api\Food\FoodAttendanceCheckRequest;
use App\Http\Requests\Api\Food\FoodAttendanceHistoryRequest;
use App\Http\Resources\Api\Food\AttendanceResource;
use App\Models\AttendanceLog;
use App\Services\Food\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends FoodApiController
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {}

    public function today(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        $log = AttendanceLog::query()
            ->with(['foodBranch', 'employee'])
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', Carbon::today()->toDateString())
            ->first();

        return $this->success([
            'attendance' => $log ? (new AttendanceResource($log))->resolve() : null,
        ]);
    }

    public function history(FoodAttendanceHistoryRequest $request): JsonResponse
    {
        $employee = $request->user()->employee;
        $from = $request->fromDate();
        $to = $request->toDate();

        $paginator = AttendanceLog::query()
            ->with(['foodBranch', 'employee'])
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', '>=', $from->toDateString())
            ->whereDate('work_date', '<=', $to->toDateString())
            ->orderByDesc('work_date')
            ->paginate($request->perPage());

        return $this->success([
            'from' => $from->toDateString(),
            'to' => $to->copy()->startOfDay()->toDateString(),
            'items' => AttendanceResource::collection($paginator->getCollection())->resolve(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function checkIn(FoodAttendanceCheckRequest $request): JsonResponse
    {
        try {
            $log = $this->attendanceService->checkIn(
                $request->user(),
                (int) $request->validated('branch_id'),
                (string) $request->validated('qr_token'),
                (float) $request->validated('latitude'),
                (float) $request->validated('longitude'),
            );
        } catch (AttendanceException $e) {
            return $this->failure($e->getMessage(), $e->errorCode, $e->httpStatus);
        }

        $log->load(['foodBranch', 'employee']);

        return $this->success(
            ['attendance' => (new AttendanceResource($log))->resolve()],
            'Đã ghi nhận giờ vào ca.',
            201
        );
    }

    public function checkOut(FoodAttendanceCheckRequest $request): JsonResponse
    {
        try {
            $log = $this->attendanceService->checkOut(
                $request->user(),
                (int) $request->validated('branch_id'),
                (string) $request->validated('qr_token'),
                (float) $request->validated('latitude'),
                (float) $request->validated('longitude'),
            );
        } catch (AttendanceException $e) {
            return $this->failure($e->getMessage(), $e->errorCode, $e->httpStatus);
        }

        $log->load(['foodBranch', 'employee']);

        return $this->success(
            ['attendance' => (new AttendanceResource($log))->resolve()],
            'Đã ghi nhận giờ ra ca.'
        );
    }
}
