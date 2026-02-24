<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\TaiChinhController;
use App\Http\Controllers\TaiChinh\GiaoDichController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Chuẩn bị cho app mobile
|--------------------------------------------------------------------------
| Prefix: /api (tự động). Phiên bản: /v1.
| Auth: Bearer token (Laravel Sanctum). Đăng nhập: POST /api/v1/login.
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// --- V1: API cho app mobile ---
Route::prefix('v1')->name('api.v1.')->group(function () {
    // Auth (không cần token)
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('logout');

    // Các route cần auth:sanctum — dùng chung logic với web
    Route::middleware('auth:sanctum')->group(function () {
        // Tài chính: insight payload (5 tầng + prompt)
        Route::get('/tai-chinh/insight-payload', [TaiChinhController::class, 'insightPayload'])->name('tai-chinh.insight-payload');
        // Projection theo scenario (months, extra_income_per_month, ...)
        Route::get('/tai-chinh/projection', [TaiChinhController::class, 'projection'])->name('tai-chinh.projection');
        // Phản hồi insight (insight_hash, feedback_type, reason_code, ...)
        Route::post('/tai-chinh/insight-feedback', [TaiChinhController::class, 'storeInsightFeedback'])->name('tai-chinh.insight-feedback');
        // Danh sách giao dịch (page, per_page, stk, loai, q, category_id)
        Route::get('/tai-chinh/giao-dich', [GiaoDichController::class, 'giaoDichJson'])->name('tai-chinh.giao-dich');
    });
});
