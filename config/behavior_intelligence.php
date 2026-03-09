<?php

return [
    'enabled' => env('BEHAVIOR_INTELLIGENCE_ENABLED', true),

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
            'deadline_boost_hours' => (float) env('EXECUTION_PRIORITY_DEADLINE_BOOST_HOURS', 4),
            'threshold_high' => (float) env('EXECUTION_PRIORITY_THRESHOLD_HIGH', 0.65),
            'threshold_medium' => (float) env('EXECUTION_PRIORITY_THRESHOLD_MEDIUM', 0.40),
        ],
        'failure_detection' => [
            'skip_streak_threshold' => (int) env('EXECUTION_FAILURE_SKIP_STREAK', 3),
            'delay_count_threshold' => (int) env('EXECUTION_FAILURE_DELAY_COUNT', 5),
        ],
        'focus_planning' => [
            'default_available_minutes' => (int) env('EXECUTION_FOCUS_AVAILABLE_MINUTES', 120),
            'default_task_minutes' => (int) env('EXECUTION_FOCUS_DEFAULT_TASK_MINUTES', 30),
        ],
    ],
];
