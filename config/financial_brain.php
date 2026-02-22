<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Financial Brain — Chuẩn hóa risk, runway, consistency
    |--------------------------------------------------------------------------
    | Một nguồn thu (effective_income), 4 trụ risk, runway, validator.
    */
    'runway' => [
        'min_surplus_months_strong' => (int) env('FINANCIAL_RUNWAY_STRONG_MONTHS', 6),
        'min_balance_strong_vnd' => (int) env('FINANCIAL_MIN_BALANCE_STRONG_VND', 5_000_000),
    ],
    'risk' => [
        'cashflow_trumps_leverage' => (bool) env('FINANCIAL_RISK_CASHFLOW_TRUMPS', true),
        'max_risk_when_strong_cashflow' => 'warning',
        'runway_good_months' => (int) env('FINANCIAL_RISK_RUNWAY_GOOD', 6),
        'runway_critical_months' => (int) env('FINANCIAL_RISK_RUNWAY_CRITICAL', 0),
        'dti_warning' => (float) env('FINANCIAL_RISK_DTI_WARNING', 0.8),
        'dti_danger' => (float) env('FINANCIAL_RISK_DTI_DANGER', 1.0),
        'leverage_danger' => (float) env('FINANCIAL_RISK_LEVERAGE_DANGER', -100_000_000),
        'leverage_critical' => (float) env('FINANCIAL_RISK_LEVERAGE_CRITICAL', -500_000_000),
        // Absolute burn dampening: thâm hụt dưới ngưỡng → trừ điểm rawScore (tránh Cực cao khi burn micro).
        'burn_dampening_absolute_vnd' => (int) env('FINANCIAL_RISK_BURN_DAMPENING_VND', 500_000),
        'burn_dampening_points' => (int) env('FINANCIAL_RISK_BURN_DAMPENING_POINTS', 1),
        // Data confidence: nhân rawScore theo months_with_data (user mới không bị phán quá nặng).
        'confidence_bands' => [
            ['max_months' => 1, 'multiplier' => (float) env('FINANCIAL_RISK_CONFIDENCE_MULT_0_1', 0.6)],
            ['max_months' => 4, 'multiplier' => (float) env('FINANCIAL_RISK_CONFIDENCE_MULT_2_4', 0.8)],
            ['max_months' => 999, 'multiplier' => 1.0],
        ],
        // Scale tier: quy mô chi tiêu nhỏ → giảm severity (micro imbalance ≠ structural collapse).
        'scale_tier_expense_vnd' => [
            'small' => (int) env('FINANCIAL_SCALE_TIER_SMALL_VND', 1_000_000),
            'medium' => (int) env('FINANCIAL_SCALE_TIER_MEDIUM_VND', 5_000_000),
        ],
        'scale_factor_small' => (float) env('FINANCIAL_SCALE_FACTOR_SMALL', 0.7),
        'scale_factor_medium' => (float) env('FINANCIAL_SCALE_FACTOR_MEDIUM', 0.85),
    ],
    /*
    | Vị thế tài chính (position): ngưỡng nợ ròng VND, lãi suất, đáo hạn, tỷ lệ thu/nợ → risk level.
    | Dùng bởi PositionRiskScoringService / FinancialPositionService.
    */
    'position_risk' => [
        'leverage_vnd' => [
            'critical' => (float) env('POSITION_RISK_LEVERAGE_CRITICAL_VND', 500_000_000),
            'danger' => (float) env('POSITION_RISK_LEVERAGE_DANGER_VND', 100_000_000),
            'warning' => (float) env('POSITION_RISK_LEVERAGE_WARNING_VND', 10_000_000),
        ],
        'interest_rate_annual_pct' => [
            'high' => (float) env('POSITION_RISK_RATE_HIGH_PCT', 24),
            'medium' => (float) env('POSITION_RISK_RATE_MEDIUM_PCT', 12),
        ],
        'interest_rate_points' => [
            'high' => (int) env('POSITION_RISK_RATE_HIGH_POINTS', 2),
            'medium' => (int) env('POSITION_RISK_RATE_MEDIUM_POINTS', 1),
        ],
        'due_date' => [
            'overdue_points' => (int) env('POSITION_RISK_DUE_OVERDUE_POINTS', 3),
            'within_days' => (int) env('POSITION_RISK_DUE_WITHIN_DAYS', 30),
            'within_days_points' => (int) env('POSITION_RISK_DUE_WITHIN_POINTS', 1),
        ],
        'receivable_debt_ratio' => [
            'warning_below' => (float) env('POSITION_RISK_RECV_DEBT_RATIO_WARNING', 0.5),
            'points' => (int) env('POSITION_RISK_RECV_DEBT_POINTS', 2),
        ],
        'debt_only_no_receivable_points' => (int) env('POSITION_RISK_DEBT_ONLY_POINTS', 2),
        'bands' => [
            ['min_score' => (int) env('POSITION_RISK_BAND_HIGH_MIN', 5), 'level' => 'high', 'label' => 'Cao', 'color' => 'red'],
            ['min_score' => (int) env('POSITION_RISK_BAND_MEDIUM_MIN', 2), 'level' => 'medium', 'label' => 'Trung bình', 'color' => 'yellow'],
            ['min_score' => 0, 'level' => 'low', 'label' => 'Thấp', 'color' => 'green'],
        ],
    ],
    'consistency' => [
        'min_income_no_income_cause_vnd' => (int) env('FINANCIAL_CONSISTENCY_MIN_INCOME_VND', 1_000_000),
        'min_balance_positive_for_no_income_cause' => (int) env('FINANCIAL_CONSISTENCY_MIN_BALANCE_VND', 0),
    ],
    /*
    | Liquidity: số dư thực → available for decision.
    | liquid_balance = tổng số dư TK liên kết.
    | committed_outflows_30d = trả nợ + chi định kỳ trong ~30 ngày (tháng đầu).
    | available_liquidity = liquid_balance - committed_outflows_30d (số dùng để ra quyết định).
    */
    'liquidity' => [
        'use_liquid_balance_as_start' => (bool) env('FINANCIAL_USE_LIQUID_BALANCE', true),
        'use_available_liquidity_as_start' => (bool) env('FINANCIAL_USE_AVAILABLE_LIQUIDITY_START', true),
        'committed_days' => (int) env('FINANCIAL_LIQUIDITY_COMMITTED_DAYS', 30),
    ],
    /*
    | Materiality: thâm hụt dưới ngưỡng → không coi là "khủng hoảng", insight nói nhẹ hơn.
    */
    'materiality' => [
        'deficit_absolute_vnd' => (int) env('FINANCIAL_MATERIALITY_DEFICIT_VND', 1_000_000),
        'deficit_pct_income' => (float) env('FINANCIAL_MATERIALITY_DEFICIT_PCT', 0.05),
    ],
    /*
    | Quy mô thâm hụt: tiny / moderate / significant (để insight phân tầng).
    */
    'scale' => [
        'deficit_tiny_vnd' => (int) env('FINANCIAL_SCALE_DEFICIT_TINY_VND', 1_000_000),
        'deficit_moderate_vnd' => (int) env('FINANCIAL_SCALE_DEFICIT_MODERATE_VND', 10_000_000),
    ],
    /*
    | Cognitive Layer (GPT synthesis trên narrative engine).
    | Chỉ diễn giải, trade-off, luồng tư duy; không tính lại state/risk.
    */
    'cognitive_layer' => [
        'use_cognitive_layer' => (bool) env('FINANCIAL_USE_COGNITIVE_LAYER', false),
        'confidence_threshold' => (float) env('FINANCIAL_COGNITIVE_CONFIDENCE_THRESHOLD', 0.6),
        'cache_ttl_hours' => (int) env('FINANCIAL_INSIGHT_AI_CACHE_TTL_HOURS', 24),
        'timeout_seconds' => (int) env('FINANCIAL_COGNITIVE_TIMEOUT', 20),
    ],
];
