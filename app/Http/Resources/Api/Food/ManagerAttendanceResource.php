<?php

namespace App\Http\Resources\Api\Food;

use App\Models\AttendanceLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Attendance + employee summary cho manager. */
class ManagerAttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var AttendanceLog $log */
        $log = $this->resource;
        $base = (new AttendanceResource($log))->resolve();
        $employee = $log->relationLoaded('employee') ? $log->employee : $log->employee()->with('user')->first();

        $base['employee'] = $employee ? [
            'id' => $employee->id,
            'position' => $employee->position,
            'name' => $employee->user?->name,
            'email' => $employee->user?->email,
        ] : null;

        return $base;
    }
}
