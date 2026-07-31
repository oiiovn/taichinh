<?php

namespace App\Http\Controllers\Api\Food;

use App\Http\Resources\Api\Food\FoodBranchResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends FoodApiController
{
    public function index(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        $branches = $employee->foodBranches()->orderByDesc('employee_food_branch.is_primary')->orderBy('food_branches.name')->get();

        return $this->success(
            FoodBranchResource::collection($branches)->resolve(),
            'OK'
        );
    }
}
