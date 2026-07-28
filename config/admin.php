<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mật khẩu mở khóa khu vực Admin
    |--------------------------------------------------------------------------
    | User có is_admin vẫn phải nhập mật khẩu này mỗi phiên (hoặc sau khi hết hạn)
    | trước khi vào các trang /admin/*. Đặt trong .env: ADMIN_GATE_PASSWORD=
    */
    'gate_password' => env('ADMIN_GATE_PASSWORD', ''),

    /*
    | Thời gian giữ phiên mở khóa (phút). Null = đến khi đăng xuất.
    */
    'gate_ttl_minutes' => env('ADMIN_GATE_TTL_MINUTES', 120),

    'session_key' => 'admin_gate_unlocked_at',
];
