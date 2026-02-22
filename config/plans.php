<?php

return [
    'term_months' => 3,
    /** Kỳ hạn cho phép chọn: 3, 6, 12 tháng. */
    'term_options' => [3, 6, 12],
    /** Thứ tự gói từ thấp đến cao (chỉ cho phép nâng cấp, không hạ gói). */
    'order' => ['basic' => 0, 'starter' => 1, 'pro' => 2, 'team' => 3, 'company' => 4, 'corporate' => 5],
    'list' => [
        'basic' => ['name' => 'BASIC', 'price' => 150000, 'max_accounts' => 1],
        'starter' => ['name' => 'STARTER', 'price' => 250000, 'max_accounts' => 3],
        'pro' => ['name' => 'PRO', 'price' => 450000, 'max_accounts' => 5],
        'team' => ['name' => 'TEAM', 'price' => 750000, 'max_accounts' => 10],
        'company' => ['name' => 'COMPANY', 'price' => 1750000, 'max_accounts' => 25],
        'corporate' => ['name' => 'CORPORATE', 'price' => 3250000, 'max_accounts' => 50],
    ],
];
