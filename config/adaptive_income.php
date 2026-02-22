<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Adaptive Income Intelligence Engine
    |--------------------------------------------------------------------------
    | EWMA, volatility-driven α, shock detection, dynamic window, reliability weight.
    | Không hardcode 3/6 tháng hay ngưỡng cố định — adaptive theo từng user.
    */
    'ewma' => [
        'alpha_min' => (float) env('ADAPTIVE_INCOME_ALPHA_MIN', 0.15),
        'alpha_max' => (float) env('ADAPTIVE_INCOME_ALPHA_MAX', 0.6),
        'volatility_high_ratio' => (float) env('ADAPTIVE_VOLATILITY_HIGH_RATIO', 0.35),
        'volatility_low_ratio' => (float) env('ADAPTIVE_VOLATILITY_LOW_RATIO', 0.08),
    ],
    'window' => [
        'base_months' => (int) env('ADAPTIVE_INCOME_BASE_WINDOW', 12),
        'min_months' => (int) env('ADAPTIVE_INCOME_MIN_WINDOW', 3),
        'max_months' => (int) env('ADAPTIVE_INCOME_MAX_WINDOW', 24),
    ],
    'shock' => [
        'threshold_ratio' => (float) env('ADAPTIVE_SHOCK_THRESHOLD_RATIO', 0.4),
    ],
    'risk_percentile' => [
        'coverage_p25_fallback' => (float) env('ADAPTIVE_RISK_COVERAGE_P25_FALLBACK', 0.15),
        'coverage_p50_fallback' => (float) env('ADAPTIVE_RISK_COVERAGE_P50_FALLBACK', 0.35),
    ],
];
