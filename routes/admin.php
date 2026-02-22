<?php

use Illuminate\Support\Facades\Route;
use App\Models\PaymentConfig;
use App\Models\Pay2sApiConfig;

/*
|--------------------------------------------------------------------------
| Luồng: Admin (chỉ user có is_admin = true)
| Prefix: /admin | Tên route: admin.*
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->middleware(['auth', 'admin'])->name('admin.')->group(function () {
    Route::get('/', function () {
        return redirect()->route('admin.users.index');
    })->name('index');

    Route::get('/he-thong', function () {
        return view('pages.admin.he-thong', [
            'title' => 'Hệ thống',
            'paymentConfig' => PaymentConfig::getConfig(),
            'pay2sApiConfig' => Pay2sApiConfig::first(),
        ]);
    })->name('he-thong');

    Route::put('/he-thong/thanh-toan', [\App\Http\Controllers\Admin\PaymentConfigController::class, 'update'])->name('he-thong.payment.update');
    Route::put('/he-thong/pay2s-api', [\App\Http\Controllers\Admin\Pay2sApiConfigController::class, 'update'])->name('he-thong.pay2s-api.update');

    Route::get('/lich-su-giao-dich', [\App\Http\Controllers\Admin\TransactionHistoryController::class, 'index'])->name('lich-su-giao-dich.index');
    Route::post('/lich-su-giao-dich/sync', [\App\Http\Controllers\Admin\TransactionHistoryController::class, 'sync'])->name('lich-su-giao-dich.sync');

    Route::resource('users', \App\Http\Controllers\Admin\UserController::class)->names('users');

    Route::prefix('brain')->name('brain.')->group(function () {
        Route::get('/{user}', [\App\Http\Controllers\Admin\BrainMonitorController::class, 'show'])->name('monitor');
    });
});
