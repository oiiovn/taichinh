<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cấu trúc tài chính: Stability, DSCR, Confidence, Drift, Adaptive threshold
    |--------------------------------------------------------------------------
    */
    'stability' => [
        'weight_volatility_adjusted' => (float) env('STRUCTURE_STABILITY_WEIGHT_VOL', 0.4),
        'weight_recurring_ratio' => (float) env('STRUCTURE_STABILITY_WEIGHT_RECURRING', 0.35),
        'weight_month_consistency' => (float) env('STRUCTURE_STABILITY_WEIGHT_CONSISTENCY', 0.25),
        'month_consistency_tolerance_pct' => (float) env('STRUCTURE_MONTH_CONSISTENCY_TOLERANCE', 0.20),
    ],
    'dscr' => [
        'min_safe' => (float) env('STRUCTURE_DSCR_MIN_SAFE', 1.2),
        'warning' => (float) env('STRUCTURE_DSCR_WARNING', 1.0),
    ],
    'confidence' => [
        'std_dev_multiplier' => (float) env('STRUCTURE_CONFIDENCE_STD_MULT', 1.5),
    ],
    'drift' => [
        'slope_pct_threshold' => (float) env('STRUCTURE_DRIFT_SLOPE_PCT_THRESHOLD', 5.0),
    ],
    'adaptive_threshold' => [
        'spike_multiplier_base' => (float) env('STRUCTURE_SPIKE_MULTIPLIER_BASE', 2.5),
        'spike_multiplier_volatility_factor' => (float) env('STRUCTURE_SPIKE_VOL_FACTOR', 0.5),
    ],
];
