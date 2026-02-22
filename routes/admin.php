<?php

use Illuminate\Support\Facades\Route;
use App\Models\PaymentConfig;
use App\Models\Pay2sApiConfig;
use App\Models\PlanConfig;

/*
|--------------------------------------------------------------------------
| Luồng: Admin (chỉ user có is_admin = true)
| Prefix: /admin | Tên route: admin.*
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->middleware(['auth', 'admin'])->name('admin.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'index'])->name('index');

    Route::get('/he-thong', function () {
        return view('pages.admin.he-thong', [
            'title' => 'Hệ thống',
            'paymentConfig' => PaymentConfig::getConfig(),
            'pay2sApiConfig' => Pay2sApiConfig::first(),
            'planConfig' => PlanConfig::getFullConfig(),
        ]);
    })->name('he-thong');

    Route::put('/he-thong/thanh-toan', [\App\Http\Controllers\Admin\PaymentConfigController::class, 'update'])->name('he-thong.payment.update');
    Route::put('/he-thong/pay2s-api', [\App\Http\Controllers\Admin\Pay2sApiConfigController::class, 'update'])->name('he-thong.pay2s-api.update');
    Route::put('/he-thong/plans', [\App\Http\Controllers\Admin\PlanConfigController::class, 'update'])->name('he-thong.plans.update');
    Route::post('/he-thong/plans/adjust-prices', [\App\Http\Controllers\Admin\PlanConfigController::class, 'adjustPrices'])->name('he-thong.plans.adjust-prices');

    Route::get('/lich-su-giao-dich', [\App\Http\Controllers\Admin\TransactionHistoryController::class, 'index'])->name('lich-su-giao-dich.index');
    Route::post('/lich-su-giao-dich/sync', [\App\Http\Controllers\Admin\TransactionHistoryController::class, 'sync'])->name('lich-su-giao-dich.sync');

    Route::resource('users', \App\Http\Controllers\Admin\UserController::class)->names('users');

    Route::get('/broadcasts', [\App\Http\Controllers\Admin\BroadcastController::class, 'index'])->name('broadcasts.index');
    Route::get('/broadcasts/create', [\App\Http\Controllers\Admin\BroadcastController::class, 'create'])->name('broadcasts.create');
    Route::post('/broadcasts', [\App\Http\Controllers\Admin\BroadcastController::class, 'store'])->name('broadcasts.store');

    Route::prefix('brain')->name('brain.')->group(function () {
        Route::get('/{user}', [\App\Http\Controllers\Admin\BrainMonitorController::class, 'show'])->name('monitor');
    });
});
