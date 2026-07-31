<?php

namespace App\Http\Resources\Api\Food;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Employee */
class ManagerEmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'active' => (bool) $this->active,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'branches' => FoodBranchResource::collection($this->whenLoaded('foodBranches'))->resolve(),
        ];
    }
}
