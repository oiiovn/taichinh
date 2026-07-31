<?php

namespace App\Http\Controllers\Api\Food;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

abstract class FoodApiController extends Controller
{
    protected function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function failure(string $message, string $code, int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => ['code' => $code],
        ], $status);
    }
}
