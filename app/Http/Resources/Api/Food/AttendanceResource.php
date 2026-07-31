<?php

namespace App\Http\Resources\Api\Food;

use App\Models\AttendanceLog;
use App\Services\Food\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AttendanceLog */
class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var AttendanceLog $log */
        $log = $this->resource;
        $employee = $log->relationLoaded('employee') ? $log->employee : $log->employee()->first();
        $state = app(AttendanceService::class)->stateOf($log);

        $status = match ($state) {
            AttendanceService::STATE_CHECKED_IN => 'checked_in',
            AttendanceService::STATE_CHECKED_OUT => 'checked_out',
            default => 'none',
        };

        $lateMinutes = null;
        if ($employee && $log->check_in_at) {
            $lateMinutes = $employee->lateMinutesForCheckIn($log->check_in_at, $log->work_date);
        }

        return [
            'id' => $log->id,
            'work_date' => $log->work_date?->toDateString(),
            'status' => $status,
            'check_in_at' => optional($log->check_in_at)?->toIso8601String(),
            'check_out_at' => optional($log->check_out_at)?->toIso8601String(),
            'work_minutes' => $log->work_minutes,
            'late_minutes' => $lateMinutes,
            'check_in_method' => $log->check_in_method,
            'check_out_method' => $log->check_out_method,
            'check_in_distance_meters' => $log->check_in_distance_meters,
            'check_out_distance_meters' => $log->check_out_distance_meters,
            'note' => $log->note,
            'branch' => $log->foodBranch
                ? (new FoodBranchResource($log->foodBranch))->resolve()
                : null,
        ];
    }
}
