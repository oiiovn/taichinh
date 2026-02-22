<?php

return [
    'gpt' => [
        'enabled' => env('GPT_CLASSIFICATION_ENABLED', false),
        'api_key' => env('OPENAI_API_KEY', ''),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'confidence_threshold' => (float) env('GPT_CLASSIFICATION_CONFIDENCE_THRESHOLD', 0.7),
        'cache_ttl_days' => (int) env('GPT_CLASSIFICATION_CACHE_DAYS', 7),
    ],
    'keyword_fallback' => [
        'enabled' => true,
        'confidence' => 0.6,
        'rules' => [
            ['keywords' => ['tien nha', 'tiền nhà', 'tien nha thang', 'rent'], 'category' => 'Hóa đơn & tiện ích', 'direction' => 'OUT'],
            ['keywords' => ['cho vu mua ao', 'mua ao', 'mua quan', 'mua sắm', 'shopping'], 'category' => 'Mua sắm', 'direction' => 'OUT'],
            ['keywords' => ['an uong', 'ăn uống', 'cafe', 'food', 'grab food'], 'category' => 'Ăn uống', 'direction' => 'OUT'],
            ['keywords' => ['di chuyen', 'di chuyển', 'grab', 'taxi', 'xang', 'xăng'], 'category' => 'Di chuyển', 'direction' => 'OUT'],
            ['keywords' => ['luong', 'lương', 'salary'], 'category' => 'Lương', 'direction' => 'IN'],
            ['keywords' => ['dien nuoc', 'điện nước', 'hoa don', 'hóa đơn'], 'category' => 'Hóa đơn & tiện ích', 'direction' => 'OUT'],
        ],
    ],
    'recurring' => [
        'min_transactions' => (int) env('RECURRING_MIN_TRANSACTIONS', 3),
        'max_transactions_analyze' => (int) env('RECURRING_MAX_ANALYZE', 12),
        'interval_days_min' => (float) env('RECURRING_INTERVAL_DAYS_MIN', 25),
        'interval_days_max' => (float) env('RECURRING_INTERVAL_DAYS_MAX', 35),
        'interval_std_max' => (float) env('RECURRING_INTERVAL_STD_MAX', 5),
        'amount_cv_max' => (float) env('RECURRING_AMOUNT_CV_MAX', 0.10),
        'match_confidence_threshold' => (float) env('RECURRING_MATCH_CONFIDENCE', 0.5),
        'amount_tolerance_pct' => (float) env('RECURRING_AMOUNT_TOLERANCE_PCT', 0.15),
        'date_tolerance_days' => (int) env('RECURRING_DATE_TOLERANCE_DAYS', 5),
    ],
];
