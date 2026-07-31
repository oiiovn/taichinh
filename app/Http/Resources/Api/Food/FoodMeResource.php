<?php

namespace App\Http\Resources\Api\Food;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FoodMeResource extends JsonResource
{
    /** @param  User  $resource */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $employee = $user->employee;
        $branches = $employee
            ? FoodBranchResource::collection($employee->foodBranches)->resolve()
            : [];

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'employee' => $employee ? [
                'id' => $employee->id,
                'position' => $employee->position,
                'active' => (bool) $employee->active,
                'salary_type' => $employee->salary_type,
                'apply_late_penalty' => (bool) $employee->apply_late_penalty,
                'shift_start_time' => $employee->shiftStartTimeHi(),
            ] : null,
            'permissions' => [
                'can_use_food_employee' => $user->canUseFoodEmployee(),
                'can_use_qr_cham_cong' => $user->canUseQrChamCong(),
                'can_manage_food_cham_cong' => $user->canManageFoodChamCong(),
                'can_manage_food_employees' => $user->canManageFoodEmployees(),
            ],
            'branches' => $branches,
        ];
    }
}
