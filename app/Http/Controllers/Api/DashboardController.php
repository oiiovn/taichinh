<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TaiChinh\DashboardBlockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Lazy dashboard API: từng block (cards, analytics, debt, projection).
 * Frontend: render skeleton → fetch từng block.
 */
class DashboardController extends Controller
{
    public function __construct(
        protected DashboardBlockService $blockService
    ) {}

    public function cards(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        try {
            $data = $this->blockService->buildCards($user);
            return response()->json($data);
        } catch (\Throwable $e) {
            Log::error('DashboardController@cards: ' . $e->getMessage(), ['user_id' => $user->id, 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Không tải được dữ liệu thẻ.'], 500);
        }
    }

    public function analytics(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        try {
            $data = $this->blockService->buildAnalytics($user, $request);
            return response()->json($data);
        } catch (\Throwable $e) {
            Log::error('DashboardController@analytics: ' . $e->getMessage(), ['user_id' => $user->id, 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Không tải được phân tích.'], 500);
        }
    }

    public function debt(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        try {
            $data = $this->blockService->buildDebt($user);
            return response()->json($data);
        } catch (\Throwable $e) {
            Log::error('DashboardController@debt: ' . $e->getMessage(), ['user_id' => $user->id, 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Không tải được dữ liệu nợ.'], 500);
        }
    }

    public function projection(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        try {
            $data = $this->blockService->buildProjection($user, $request);
            return response()->json($data);
        } catch (\Throwable $e) {
            Log::error('DashboardController@projection: ' . $e->getMessage(), ['user_id' => $user->id, 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Không tính được dự báo.'], 500);
        }
    }
}
