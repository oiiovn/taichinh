<?php

namespace App\Http\Resources\Api\Food;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\FoodBranch */
class FoodBranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'check_in_radius_meters' => (int) ($this->check_in_radius_meters ?: 100),
            'is_primary' => (bool) ($this->pivot?->is_primary ?? false),
        ];
    }
}
