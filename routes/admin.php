<?php

use Illuminate\Support\Facades\Route;
use App\Models\FoodReportBonusTier;
use App\Models\FoodBuffIncomeTarget;
use App\Models\FoodGiftConfig;
use App\Models\PaymentConfig;
use App\Models\Pay2sApiConfig;
use App\Models\PlanConfig;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Luồng: Admin (chỉ user có is_admin = true)
| Prefix: /admin | Tên route: admin.*
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->middleware(['auth', 'admin'])->name('admin.')->group(function () {
    Route::get('/unlock', [\App\Http\Controllers\Admin\AdminGateController::class, 'show'])->name('gate.show');
    Route::post('/unlock', [\App\Http\Controllers\Admin\AdminGateController::class, 'unlock'])->name('gate.unlock');
    Route::post('/lock', [\App\Http\Controllers\Admin\AdminGateController::class, 'lock'])->name('gate.lock');

    Route::middleware('admin.gate')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'index'])->name('index');

    Route::get('/he-thong', function () {
        return view('pages.admin.he-thong', [
            'title' => 'Hệ thống',
            'paymentConfig' => PaymentConfig::getConfig(),
            'pay2sApiConfig' => Pay2sApiConfig::first(),
            'planConfig' => PlanConfig::getFullConfig(),
            'foodGiftConfig' => FoodGiftConfig::getConfig(),
            'bonusTiers' => FoodReportBonusTier::orderByDesc('min_total_cost')->get(),
            'foodTargetUsers' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'foodIncomeTargets' => FoodBuffIncomeTarget::query()
                ->with('user:id,name,email')
                ->orderByDesc('target_month')
                ->orderByDesc('id')
                ->limit(30)
                ->get(),
        ]);
    })->name('he-thong');

    Route::post('/he-thong/bonus-tiers', [\App\Http\Controllers\Admin\FoodReportBonusTierController::class, 'store'])->name('he-thong.bonus-tiers.store');
    Route::put('/he-thong/bonus-tiers/{bonusTier}', [\App\Http\Controllers\Admin\FoodReportBonusTierController::class, 'update'])->name('he-thong.bonus-tiers.update');
    Route::delete('/he-thong/bonus-tiers/{bonusTier}', [\App\Http\Controllers\Admin\FoodReportBonusTierController::class, 'destroy'])->name('he-thong.bonus-tiers.destroy');

    Route::put('/he-thong/thanh-toan', [\App\Http\Controllers\Admin\PaymentConfigController::class, 'update'])->name('he-thong.payment.update');
    Route::post('/he-thong/food-gift-config', [\App\Http\Controllers\Admin\FoodGiftConfigController::class, 'update'])->name('he-thong.food-gift-config.update');
    Route::post('/he-thong/food-income-targets', [\App\Http\Controllers\Admin\FoodBuffIncomeTargetController::class, 'upsert'])->name('he-thong.food-income-targets.upsert');
    Route::put('/he-thong/pay2s-api', [\App\Http\Controllers\Admin\Pay2sApiConfigController::class, 'update'])->name('he-thong.pay2s-api.update');
    Route::put('/he-thong/plans', [\App\Http\Controllers\Admin\PlanConfigController::class, 'update'])->name('he-thong.plans.update');
    Route::post('/he-thong/plans/adjust-prices', [\App\Http\Controllers\Admin\PlanConfigController::class, 'adjustPrices'])->name('he-thong.plans.adjust-prices');

    Route::get('/lich-su-giao-dich', [\App\Http\Controllers\Admin\TransactionHistoryController::class, 'index'])->name('lich-su-giao-dich.index');
    Route::post('/lich-su-giao-dich/sync', [\App\Http\Controllers\Admin\TransactionHistoryController::class, 'sync'])->name('lich-su-giao-dich.sync');
    Route::delete('/lich-su-giao-dich/{transaction}', [\App\Http\Controllers\Admin\TransactionHistoryController::class, 'destroy'])->name('lich-su-giao-dich.destroy');

    Route::resource('users', \App\Http\Controllers\Admin\UserController::class)->names('users');

    Route::get('/signup-qr', [\App\Http\Controllers\Admin\SignupQrController::class, 'index'])->name('signup-qr');

    Route::get('/broadcasts', [\App\Http\Controllers\Admin\BroadcastController::class, 'index'])->name('broadcasts.index');
    Route::get('/broadcasts/create', [\App\Http\Controllers\Admin\BroadcastController::class, 'create'])->name('broadcasts.create');
    Route::post('/broadcasts', [\App\Http\Controllers\Admin\BroadcastController::class, 'store'])->name('broadcasts.store');
    Route::delete('/broadcasts/{broadcast}', [\App\Http\Controllers\Admin\BroadcastController::class, 'destroy'])->name('broadcasts.destroy');

    Route::prefix('brain')->name('brain.')->group(function () {
        Route::get('/{user}', [\App\Http\Controllers\Admin\BrainMonitorController::class, 'show'])->name('monitor');
    });
    });
});
