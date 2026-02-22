<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Income Purity — Lọc thu vận hành, confidence, one-off
    |--------------------------------------------------------------------------
    | Momentum chạy trên operational_income (đã loại vay/thu hồi nợ/chuyển nội bộ),
    | mỗi giao dịch có income_score, one-off bị down-weight.
    */
    'operational' => [
        'category_names' => [
            'Lương',
            'Thưởng',
            'Kinh doanh',
            'Đầu tư / Lãi',
        ],
        'exclude_category_names' => [
            'Cho vay thu hồi',
            'Quà tặng / Nhận chuyển',
        ],
        'exclude_keywords' => [
            'vay ',
            ' vay',
            'rút vốn',
            'rut von',
            'chuyển nội bộ',
            'chuyen noi bo',
            'internal transfer',
        ],
    ],
    'confidence' => [
        'by_category' => [
            'Lương' => 1.0,
            'Thưởng' => 0.9,
            'Kinh doanh' => 0.85,
            'Đầu tư / Lãi' => 0.8,
            'Khác (thu)' => 0.5,
        ],
        'unclassified' => 0.5,
        'pending_classification' => 0.4,
    ],
    'one_off' => [
        'median_multiplier' => (float) env('INCOME_PURITY_ONEOFF_MULTIPLIER', 3),
        'score_cap' => (float) env('INCOME_PURITY_ONEOFF_SCORE_CAP', 0.2),
    ],
    'stability' => [
        'min_mean_for_index' => (float) env('INCOME_PURITY_STABILITY_MIN_MEAN', 100_000),
    ],
];
