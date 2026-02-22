<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Financial Role — Vai trò dòng tiền: Operating vs Financing vs Transfer
    |--------------------------------------------------------------------------
    | Projection chỉ dùng OPERATING_INCOME - OPERATING_EXPENSE - DEBT_PAYMENT.
    | FINANCING_INFLOW không bao giờ là thu nhập.
    */
    'inflow_roles' => [
        'OPERATING_INCOME' => [
            'categories' => ['Lương', 'Thưởng', 'Kinh doanh', 'Đầu tư / Lãi', 'Khác (thu)'],
            'keywords' => ['luong', 'lương', 'salary', 'thuong', 'thưởng', 'kinh doanh', 'bán hàng'],
        ],
        'FINANCING_INFLOW' => [
            'keywords' => ['vay ', ' vay', 'rút vốn', 'rut von', 'gia han vay', 'giải ngân'],
        ],
        'INTERNAL_TRANSFER' => [
            'keywords' => ['chuyển nội bộ', 'chuyen noi bo', 'internal transfer', 'chuyen khoan noi bo'],
        ],
        'ONE_OFF_INCOME' => [
            'keywords' => ['bán tài sản', 'ban tai san', 'thu hồi', 'hoàn tiền'],
        ],
        'DEBT_RETURN' => [
            'categories' => ['Cho vay thu hồi'],
            'keywords' => ['thu no', 'thu nợ', 'tra no', 'trả nợ', 'rut tra no'],
        ],
    ],
    'outflow_roles' => [
        'OPERATING_EXPENSE' => [
            'categories' => ['Ăn uống', 'Di chuyển', 'Mua sắm', 'Hóa đơn & tiện ích', 'Giải trí', 'Sức khỏe', 'Giáo dục', 'Khác (chi)'],
            'keywords' => ['an uong', 'ăn uống', 'mua sam', 'mua sắm', 'hoa don', 'hóa đơn'],
        ],
        'DEBT_PAYMENT' => [
            'categories' => ['Cho vay / Trả nợ'],
            'keywords' => ['tra no', 'trả nợ', 'rut tra no', 'lai vay', 'lãi vay', 'tien vay'],
        ],
        'INTERNAL_TRANSFER' => [
            'keywords' => ['chuyển nội bộ', 'chuyen noi bo', 'internal transfer'],
        ],
        'INVESTMENT' => [
            'categories' => ['Đầu tư'],
            'keywords' => ['đầu tư', 'dau tu', 'mua co phieu', 'tiết kiệm'],
        ],
    ],
    'behavior_inference' => [
        'spike_median_multiplier' => (float) env('FINANCIAL_ROLE_SPIKE_MULTIPLIER', 5),
    ],
];
