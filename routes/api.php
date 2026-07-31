<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\Food\AttendanceController as FoodAttendanceController;
use App\Http\Controllers\Api\Food\BranchController as FoodBranchController;
use App\Http\Controllers\Api\Food\ChamCongController as ApiFoodChamCongController;
use App\Http\Controllers\Api\Food\ManagerController as FoodManagerController;
use App\Http\Controllers\Api\Food\MeController as FoodMeController;
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

        // Lazy dashboard: từng block (cards, analytics, debt, projection)
        Route::get('/dashboard/cards', [DashboardController::class, 'cards'])->name('dashboard.cards');
        Route::get('/dashboard/analytics', [DashboardController::class, 'analytics'])->name('dashboard.analytics');
        Route::get('/dashboard/debt', [DashboardController::class, 'debt'])->name('dashboard.debt');
        Route::get('/dashboard/projection', [DashboardController::class, 'projection'])->name('dashboard.projection');

        // Food — menu theo quyền + home + chấm công (app FRESH)
        Route::get('/food/menu', [\App\Http\Controllers\Api\Food\MenuController::class, 'index'])->name('food.menu');
        Route::get('/food/home', [\App\Http\Controllers\Api\Food\HomeController::class, 'index'])->name('food.home');
        Route::get('/food/cham-cong', [ApiFoodChamCongController::class, 'index'])->name('food.cham-cong.index');
        Route::post('/food/cham-cong', [ApiFoodChamCongController::class, 'store'])->name('food.cham-cong.store');
        Route::post('/food/qr-cham-cong/scan', [\App\Http\Controllers\Api\Food\QrChamCongScanController::class, 'scan'])->name('food.qr-cham-cong.scan');
        Route::get('/food/bang-luong', [\App\Http\Controllers\Api\Food\BangLuongController::class, 'index'])->name('food.bang-luong');
    });

    // F&B mobile attendance
    Route::prefix('food')->name('food.')->middleware(['auth:sanctum', 'food.mobile.employee'])->group(function () {
        Route::get('/me', [FoodMeController::class, 'show'])->name('me');
        Route::get('/branches', [FoodBranchController::class, 'index'])->name('branches');
        Route::get('/attendance/today', [FoodAttendanceController::class, 'today'])->name('attendance.today');
        Route::get('/attendance/history', [FoodAttendanceController::class, 'history'])->name('attendance.history');
        Route::post('/attendance/check-in', [FoodAttendanceController::class, 'checkIn'])
            ->middleware('food.mobile.qr')
            ->name('attendance.check-in');
        Route::post('/attendance/check-out', [FoodAttendanceController::class, 'checkOut'])
            ->middleware('food.mobile.qr')
            ->name('attendance.check-out');
    });

    // F&B mobile manager (không bắt buộc là employee)
    Route::prefix('food/manager')->name('food.manager.')->middleware(['auth:sanctum', 'food.mobile.manager'])->group(function () {
        Route::get('/branches', [FoodManagerController::class, 'branches'])->name('branches');
        Route::get('/employees', [FoodManagerController::class, 'employees'])->name('employees');
        Route::get('/attendance/today', [FoodManagerController::class, 'attendanceToday'])->name('attendance.today');
        Route::get('/attendance/history', [FoodManagerController::class, 'attendanceHistory'])->name('attendance.history');
    });
});
