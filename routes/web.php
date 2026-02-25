<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Điểm vào route web – phân luồng rõ ràng
|--------------------------------------------------------------------------
| - auth.php  : Đăng nhập, đăng ký, đăng xuất
| - admin.php : Khu vực quản trị (/admin)
| - front.php : Giao diện chính sau đăng nhập (dashboard, profile, tài chính, ...)
*/

// Phục vụ ảnh avatar từ storage (tránh request đi qua layout → lỗi khi Auth::user() null)
Route::get('/storage/avatars/{filename}', function (string $filename) {
    $path = Storage::disk('public')->path('avatars/' . $filename);
    if (! is_file($path)) {
        abort(404);
    }
    return response()->file($path);
})->where('filename', '[^/]+');

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/front.php';
