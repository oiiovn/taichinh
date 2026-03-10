<?php

return [
    'enabled' => env('BEHAVIOR_INTELLIGENCE_ENABLED', true),

    /** Số ngày tối đa ensure instance khi mở Dự kiến (tránh loop quá lâu). */
    'instance_ensure_horizon_days' => (int) env('INSTANCE_ENSURE_HORIZON_DAYS', 90),

    /** Số lần tiếp theo tối đa hiển thị khi mở rộng task lặp ở tab Dự kiến. */
    'du_kien_expand_limit' => (int) env('DU_KIEN_EXPAND_LIMIT', 10),

    /** Routine Detection Engine: thống kê completed_at → median, stability, slot (soft signal). */
    'routine_detection' => [
        'min_samples' => (int) env('ROUTINE_MIN_SAMPLES', 3),
        'max_samples' => (int) env('ROUTINE_MAX_SAMPLES', 50),
        'confidence_threshold' => (float) env('ROUTINE_CONFIDENCE_THRESHOLD', 0.5),
        'confidence_sample_cap' => (int) env('ROUTINE_CONFIDENCE_SAMPLE_CAP', 7),
        'stability_denom_minutes' => (float) env('ROUTINE_STABILITY_DENOM', 120),
        'routine_boost_threshold' => (float) env('ROUTINE_BOOST_CONFIDENCE', 0.7),
        'routine_boost_window_minutes' => (int) env('ROUTINE_BOOST_WINDOW_MINUTES', 30),
    ],
    /** Task Energy Model — affinity theo slot (morning/afternoon/evening) từ quality = estimated/actual. */
    'energy_affinity' => [
        'min_samples' => (int) env('ENERGY_AFFINITY_MIN_SAMPLES', 6),
        'max_samples' => (int) env('ENERGY_AFFINITY_MAX_SAMPLES', 30),
        'energy_bonus' => (float) env('ENERGY_AFFINITY_BONUS', 0.08),
        'update_meta_every_n_completions' => (int) env('ENERGY_UPDATE_META_EVERY_N', 5),
    ],

    /** Behavior Drift Detection — phát hiện khi hành vi lệch khỏi routine đã học. */
    'behavior_drift' => [
        'long_window' => (int) env('BEHAVIOR_DRIFT_LONG_WINDOW', 30),
        'short_window' => (int) env('BEHAVIOR_DRIFT_SHORT_WINDOW', 5),
        'threshold_minutes' => (float) env('BEHAVIOR_DRIFT_THRESHOLD_MINUTES', 45),
        'short_variance_max' => (float) env('BEHAVIOR_DRIFT_SHORT_VARIANCE_MAX', 60),
        'routine_decay_on_drift' => (float) env('BEHAVIOR_DRIFT_ROUTINE_DECAY', 0.7),
    ],

    'layers' => [
        'identity_baseline' => true,
        'micro_event_capture' => true,
        'temporal_consistency' => true,
        'cognitive_load' => true,
        'probabilistic_truth' => true,
        'adaptive_trust' => true,
        'behavioral_anomaly' => true,
        'recovery_intelligence' => true,
        'habit_internalization' => true,
        'long_term_projection' => true,
    ],

    'identity_baseline' => [
        'bsv_scale_min' => -1.0,
        'bsv_scale_max' => 1.0,
    ],

    'micro_event' => [
        'batch_max' => (int) env('BEHAVIOR_EVENTS_BATCH_MAX', 50),
        'queue' => env('BEHAVIOR_EVENTS_QUEUE', 'default'),
    ],

    'probabilistic_truth' => [
        'threshold_require_confirmation' => (float) env('BEHAVIOR_P_REAL_THRESHOLD_CONFIRM', 0.7),
        'threshold_auto_accept' => (float) env('BEHAVIOR_P_REAL_THRESHOLD_AUTO', 0.9),
    ],

    'recovery' => [
        'stable_streak_days' => (int) env('BEHAVIOR_RECOVERY_STABLE_DAYS', 3),
        'slow_recovery_days' => (int) env('BEHAVIOR_RECOVERY_SLOW_DAYS', 5),
    ],

    'internalization' => [
        'reminder_reduction_pct' => (float) env('BEHAVIOR_INTERNALIZED_REMINDER_REDUCTION', 0.70),
        'min_completions_before_internalized' => (int) env('BEHAVIOR_INTERNALIZED_MIN_COMPLETIONS', 5),
        'max_variance_minutes' => (int) env('BEHAVIOR_INTERNALIZED_MAX_VARIANCE_MIN', 15),
    ],

    'anomaly' => [
        'sigma_threshold' => (float) env('BEHAVIOR_ANOMALY_SIGMA', 2.0),
        'max_message_per_day' => (int) env('BEHAVIOR_ANOMALY_MAX_PER_DAY', 1),
    ],

    'projection' => [
        'min_days_data' => (int) env('BEHAVIOR_PROJECTION_MIN_DAYS', 90),
        'snapshot_ttl_hours' => (int) env('BEHAVIOR_PROJECTION_SNAPSHOT_TTL', 24),
    ],

    'policy' => [
        'cli_high_threshold' => (float) env('BEHAVIOR_POLICY_CLI_HIGH', 0.7),
        'strictness_levels' => ['low', 'normal', 'high'],
    ],

    'coaching_effectiveness' => [
        'enabled' => env('BEHAVIOR_COACHING_EFFECTIVENESS_ENABLED', true),
        'min_samples_per_type' => (int) env('BEHAVIOR_COACHING_EFFECTIVENESS_MIN_SAMPLES', 3),
    ],

    'execution_intelligence' => [
        'priority_engine' => [
            'weight_urgency' => (float) env('EXECUTION_PRIORITY_WEIGHT_URGENCY', 0.30),
            'weight_impact' => (float) env('EXECUTION_PRIORITY_WEIGHT_IMPACT', 0.25),
            'weight_streak_risk' => (float) env('EXECUTION_PRIORITY_WEIGHT_STREAK_RISK', 0.15),
            'weight_program' => (float) env('EXECUTION_PRIORITY_WEIGHT_PROGRAM', 0.10),
            'weight_overdue' => (float) env('EXECUTION_PRIORITY_WEIGHT_OVERDUE', 0.10),
            'weight_deadline_pressure' => (float) env('EXECUTION_PRIORITY_WEIGHT_DEADLINE_PRESSURE', 0.10),
            'weight_energy_fit' => (float) env('EXECUTION_PRIORITY_WEIGHT_ENERGY_FIT', 0.12),
            'deadline_boost_hours' => (float) env('EXECUTION_PRIORITY_DEADLINE_BOOST_HOURS', 4),
            'missed_window_boost' => (float) env('EXECUTION_PRIORITY_MISSED_WINDOW_BOOST', 0.25),
            'energy_bonus' => (float) env('EXECUTION_ENERGY_BONUS', 0.08),
            'threshold_high' => (float) env('EXECUTION_PRIORITY_THRESHOLD_HIGH', 0.65),
            'threshold_medium' => (float) env('EXECUTION_PRIORITY_THRESHOLD_MEDIUM', 0.40),
        ],
        'failure_detection' => [
            'skip_streak_threshold' => (int) env('EXECUTION_FAILURE_SKIP_STREAK', 3),
            'delay_count_threshold' => (int) env('EXECUTION_FAILURE_DELAY_COUNT', 5),
        ],
        /** Gợi ý cập nhật ước lượng phút sau khi complete (tránh hỏi mỗi lần). */
        'duration_suggestion' => [
            'min_actual_minutes' => (int) env('DURATION_SUGGEST_MIN_ACTUAL', 3),
            'min_samples_for_suggest' => (int) env('DURATION_SUGGEST_MIN_SAMPLES', 3),
            'last_n_actuals' => (int) env('DURATION_SUGGEST_LAST_N', 5),
            'ratio_high' => (float) env('DURATION_SUGGEST_RATIO_HIGH', 1.2),
            'ratio_low' => (float) env('DURATION_SUGGEST_RATIO_LOW', 0.8),
            'deviation_vs_predicted_pct' => (float) env('DURATION_SUGGEST_DEVIATION_PCT', 0.25),
            'max_cv_last3' => (float) env('DURATION_SUGGEST_MAX_CV', 0.35),
        ],
        /** Bảo vệ duration: idle = coi session kết thúc; cap 3× estimated; sanity > 2×. */
        'focus_duration' => [
            'idle_seconds' => (int) env('FOCUS_IDLE_SECONDS', 300),
        ],
        /** Gợi ý nghỉ theo Focus Load (không ép Pomodoro). */
        'break_suggestion' => [
            'threshold_short_minutes' => (int) env('BREAK_SUGGEST_THRESHOLD_SHORT', 45),
            'threshold_long_minutes' => (int) env('BREAK_SUGGEST_THRESHOLD_LONG', 90),
            'break_duration_short' => (int) env('BREAK_SUGGEST_DURATION_SHORT', 5),
            'break_duration_long' => (int) env('BREAK_SUGGEST_DURATION_LONG', 10),
        ],
        'focus_planning' => [
            'default_available_minutes' => (int) env('EXECUTION_FOCUS_AVAILABLE_MINUTES', 120),
            'default_task_minutes' => (int) env('EXECUTION_FOCUS_DEFAULT_TASK_MINUTES', 30),
            /** Dynamic focus budget từ lịch sử: avg 7 ngày * multiplier, cap tối đa. */
            'dynamic_budget' => [
                'enabled' => env('EXECUTION_FOCUS_DYNAMIC_BUDGET', true),
                'window_days' => (int) env('EXECUTION_FOCUS_BUDGET_WINDOW_DAYS', 7),
                'multiplier' => (float) env('EXECUTION_FOCUS_BUDGET_MULTIPLIER', 0.7),
                'cap_minutes' => (int) env('EXECUTION_FOCUS_BUDGET_CAP', 240),
                'min_minutes' => (int) env('EXECUTION_FOCUS_BUDGET_MIN', 30),
            ],
        ],
    ],
];
