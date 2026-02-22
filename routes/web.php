<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Điểm vào route web – phân luồng rõ ràng
|--------------------------------------------------------------------------
| - auth.php  : Đăng nhập, đăng ký, đăng xuất
| - admin.php : Khu vực quản trị (/admin)
| - front.php : Giao diện chính sau đăng nhập (dashboard, profile, tài chính, ...)
*/

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/front.php';
