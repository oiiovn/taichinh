<?php

namespace App\Services\Food;

use App\Exceptions\Food\AttendanceException;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\FoodBranch;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Business logic mobile check-in / check-out (QR + GPS).
 * Không thay thế web ChamCong / QrChamCongController.
 */
class AttendanceService
{
    public const METHOD_QR = 'qr';

    public const STATE_NO_LOG = 'NO_LOG';

    public const STATE_CHECKED_IN = 'CHECKED_IN';

    public const STATE_CHECKED_OUT = 'CHECKED_OUT';

    /**
     * Quét QR: tiến state NO_LOG→checkIn, CHECKED_IN→checkOut; từ chối nếu đã CHECKED_OUT.
     */
    public function scanQr(
        User $user,
        int $branchId,
        string $token,
        float $latitude,
        float $longitude,
        ?CarbonInterface $at = null,
    ): AttendanceLog {
        $employee = $this->resolveActiveEmployee($user);
        $at = $at ?? now();
        $workDate = Carbon::parse($at)->startOfDay();

        $log = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $workDate->toDateString())
            ->first();

        return match ($this->stateOf($log)) {
            self::STATE_NO_LOG => $this->checkIn($user, $branchId, $token, $latitude, $longitude, $at),
            self::STATE_CHECKED_IN => $this->checkOut($user, $branchId, $token, $latitude, $longitude, $at),
            self::STATE_CHECKED_OUT => throw AttendanceException::make(
                AttendanceException::ALREADY_CHECKED_OUT,
                'Hôm nay bạn đã chấm đủ vào ca và ra ca.',
                409
            ),
        };
    }

    public function checkIn(
        User $user,
        int $branchId,
        string $token,
        float $latitude,
        float $longitude,
        ?CarbonInterface $at = null,
    ): AttendanceLog {
        $employee = $this->resolveActiveEmployee($user);
        $at = Carbon::parse($at ?? now());
        $workDate = $at->copy()->startOfDay();

        $branch = $this->resolveAssignedBranch($employee, $branchId);
        QrAttendanceToken::assertValidForBranch($token, $branchId, $at);
        $gps = GeoDistance::assertWithinBranchRadius($branch, $latitude, $longitude);

        return DB::transaction(function () use ($employee, $branch, $workDate, $at, $latitude, $longitude, $gps) {
            $log = $this->lockOrCreateTodayLog($employee->id, $workDate);

            if ($log->check_in_at) {
                throw AttendanceException::make(
                    AttendanceException::ALREADY_CHECKED_IN,
                    'Bạn đã check-in hôm nay.',
                    409
                );
            }

            $log->fill([
                'food_branch_id' => $branch->id,
                'check_in_at' => $at,
                'check_in_latitude' => $latitude,
                'check_in_longitude' => $longitude,
                'check_in_method' => self::METHOD_QR,
                'check_in_distance_meters' => $gps['distance_meters'],
            ]);
            $log->save();
            $log->refresh();

            $employee->applyLatePenaltyNote($log);

            return $log->fresh(['employee', 'foodBranch']);
        });
    }

    public function checkOut(
        User $user,
        int $branchId,
        string $token,
        float $latitude,
        float $longitude,
        ?CarbonInterface $at = null,
    ): AttendanceLog {
        $employee = $this->resolveActiveEmployee($user);
        $at = Carbon::parse($at ?? now());
        $workDate = $at->copy()->startOfDay();

        $branch = $this->resolveAssignedBranch($employee, $branchId);
        QrAttendanceToken::assertValidForBranch($token, $branchId, $at);
        $gps = GeoDistance::assertWithinBranchRadius($branch, $latitude, $longitude);

        return DB::transaction(function () use ($employee, $branch, $workDate, $at, $latitude, $longitude, $gps, $branchId) {
            $log = AttendanceLog::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $workDate->toDateString())
                ->lockForUpdate()
                ->first();

            if (! $log || ! $log->check_in_at) {
                throw AttendanceException::make(
                    AttendanceException::NOT_CHECKED_IN,
                    'Bạn chưa check-in hôm nay.',
                    409
                );
            }

            if ($log->check_out_at) {
                throw AttendanceException::make(
                    AttendanceException::ALREADY_CHECKED_OUT,
                    'Bạn đã check-out hôm nay.',
                    409
                );
            }

            if ($log->food_branch_id !== null && (int) $log->food_branch_id !== $branchId) {
                throw AttendanceException::make(
                    AttendanceException::BRANCH_NOT_ASSIGNED,
                    'Phải checkout tại cùng chi nhánh đã check-in.',
                    409
                );
            }

            $log->fill([
                'food_branch_id' => $log->food_branch_id ?? $branch->id,
                'check_out_at' => $at,
                'check_out_latitude' => $latitude,
                'check_out_longitude' => $longitude,
                'check_out_method' => self::METHOD_QR,
                'check_out_distance_meters' => $gps['distance_meters'],
            ]);
            $log->save();

            return $log->fresh(['employee', 'foodBranch']);
        });
    }

    public function stateOf(?AttendanceLog $log): string
    {
        if (! $log || ! $log->check_in_at) {
            return self::STATE_NO_LOG;
        }
        if (! $log->check_out_at) {
            return self::STATE_CHECKED_IN;
        }

        return self::STATE_CHECKED_OUT;
    }

    public function resolveActiveEmployee(User $user): Employee
    {
        $employee = $user->employee;
        if (! $employee) {
            throw AttendanceException::make(
                AttendanceException::EMPLOYEE_NOT_FOUND,
                'Bạn không phải nhân viên.',
                403
            );
        }
        if (! $employee->active) {
            throw AttendanceException::make(
                AttendanceException::EMPLOYEE_INACTIVE,
                'Tài khoản nhân viên đã ngưng hoạt động.',
                403
            );
        }

        return $employee;
    }

    public function resolveAssignedBranch(Employee $employee, int $branchId): FoodBranch
    {
        $branch = FoodBranch::query()->find($branchId);
        if (! $branch) {
            throw AttendanceException::make(
                AttendanceException::BRANCH_NOT_FOUND,
                'Không tìm thấy chi nhánh.',
                404
            );
        }

        $assigned = $employee->foodBranches()
            ->where('food_branches.id', $branchId)
            ->exists();

        if (! $assigned) {
            throw AttendanceException::make(
                AttendanceException::BRANCH_NOT_ASSIGNED,
                'Bạn chưa được phân công chi nhánh này.',
                403
            );
        }

        return $branch;
    }

    protected function lockOrCreateTodayLog(int $employeeId, Carbon $workDate): AttendanceLog
    {
        $log = AttendanceLog::query()
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', $workDate->toDateString())
            ->lockForUpdate()
            ->first();

        if ($log) {
            return $log;
        }

        try {
            return AttendanceLog::query()->create([
                'employee_id' => $employeeId,
                'work_date' => $workDate->toDateString(),
            ]);
        } catch (QueryException $e) {
            // Race: unique(employee_id, work_date) — đọc lại và lock.
            $log = AttendanceLog::query()
                ->where('employee_id', $employeeId)
                ->whereDate('work_date', $workDate->toDateString())
                ->lockForUpdate()
                ->first();

            if ($log) {
                return $log;
            }

            throw $e;
        }
    }
}
