<?php

namespace App\Http\Controllers\Api\Food;

use App\Http\Resources\Api\Food\FoodMeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends FoodApiController
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['employee.foodBranches']);

        return $this->success(
            (new FoodMeResource($user))->resolve(),
            'OK'
        );
    }
}
