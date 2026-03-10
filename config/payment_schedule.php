<?php

return [
    /*
    | Khi lịch thanh toán được gia hạn (next_due_date advance, do match giao dịch hoặc sửa tay),
    | có tự tạo task công việc cho kỳ mới không.
    */
    'create_task_on_advance' => env('PAYMENT_SCHEDULE_CREATE_TASK_ON_ADVANCE', true),

    /*
    | Giờ mặc định cho task tạo từ lịch thanh toán (HH:mm, 24h).
    */
    'default_task_time' => env('PAYMENT_SCHEDULE_DEFAULT_TASK_TIME', '21:00'),
];
