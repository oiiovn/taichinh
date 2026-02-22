<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Capital Hierarchy — Triết lý cấu trúc tài chính, không hardcode số
    |--------------------------------------------------------------------------
    | 4 trụ Capital Stability (0–1) → Maturity Stage → Strategy Doctrine.
    */

    'pillars' => [
        'cashflow_integrity' => [
            'label' => 'Cashflow Integrity',
            'components' => ['surplus_ratio', 'income_consistency', 'recurring_coverage'],
        ],
        'liquidity_depth' => [
            'label' => 'Liquidity Depth',
            'components' => ['runway_months', 'liquidity_expense_ratio'],
        ],
        'debt_load_quality' => [
            'label' => 'Debt Load Quality',
            'components' => ['debt_service_ratio', 'interest_burden', 'debt_concentration'],
        ],
        'structural_flexibility' => [
            'label' => 'Structural Flexibility',
            'components' => ['fixed_cost_ratio', 'volatility_exposure', 'financing_dependency'],
        ],
    ],

    'stages' => [
        'survival' => [
            'label' => 'Sinh tồn',
            'description' => 'Dòng tiền âm, thanh khoản thấp — ưu tiên bảo toàn.',
        ],
        'fragile' => [
            'label' => 'Mỏng manh',
            'description' => 'Dòng tiền dương nhẹ, thanh khoản mỏng — tăng buffer.',
        ],
        'fragile_cashflow' => [
            'label' => 'Mỏng manh (mất cân bằng dòng tiền)',
            'description' => 'Cấu trúc mất cân bằng, cần chỉnh dòng tiền.',
        ],
        'fragile_liquidity' => [
            'label' => 'Mỏng manh (lớp đệm mỏng)',
            'description' => 'Ổn định nhưng chưa có lớp phòng thủ.',
        ],
        'fragile_volatility' => [
            'label' => 'Mỏng manh (thu biến động)',
            'description' => 'Rủi ro do biến động, không phải do thâm hụt.',
        ],
        'stabilizing' => [
            'label' => 'Đang củng cố',
            'description' => 'Surplus ổn định, buffer đang tăng — củng cố cấu trúc.',
        ],
        'resilient' => [
            'label' => 'Bền vững',
            'description' => 'Buffer 3–6 tháng, DTI hợp lý — có dư địa.',
        ],
        'optimizing' => [
            'label' => 'Tối ưu',
            'description' => 'Cấu trúc khỏe, có dư — tối ưu dài hạn.',
        ],
        'expanding' => [
            'label' => 'Mở rộng',
            'description' => 'Đòn bẩy có kiểm soát — mở rộng có ý thức.',
        ],
    ],

    'doctrine' => [
        'survival' => [
            'priority' => 'preserve',
            'narrative_hint' => 'Ưu tiên bảo toàn thanh khoản và ổn định dòng tiền. Mục tiêu không phải tăng trưởng mà là thoát vùng nguy hiểm.',
        ],
        'fragile' => [
            'priority' => 'increase_buffer',
            'narrative_hint' => 'Tăng buffer trước khi nghĩ tới tối ưu. Độ bền cấu trúc quan trọng hơn con số dư ngắn hạn.',
        ],
        'fragile_cashflow' => [
            'priority' => 'rebalance_cashflow',
            'narrative_hint' => 'Cấu trúc mất cân bằng, cần chỉnh dòng tiền.',
        ],
        'fragile_liquidity' => [
            'priority' => 'increase_buffer',
            'narrative_hint' => 'Ổn định nhưng chưa có lớp phòng thủ. Ưu tiên tăng độ dày lớp đệm.',
        ],
        'fragile_volatility' => [
            'priority' => 'reduce_volatility_exposure',
            'narrative_hint' => 'Rủi ro do biến động thu nhập, không phải do thâm hụt. Nên dự phòng 6 tháng chi trước khi tối ưu.',
        ],
        'stabilizing' => [
            'priority' => 'consolidate',
            'narrative_hint' => 'Bạn đang ở giai đoạn củng cố. Mục tiêu không phải tối đa hóa tăng trưởng, mà là nâng độ bền cấu trúc. Tăng buffer trước khi tối ưu.',
        ],
        'resilient' => [
            'priority' => 'optimize',
            'narrative_hint' => 'Cấu trúc đã đủ bền — có thể tối ưu nợ và mục tiêu dài hạn một cách có ý thức.',
        ],
        'optimizing' => [
            'priority' => 'optimize',
            'narrative_hint' => 'Cấu trúc tài chính khỏe. Tập trung tối ưu và mục tiêu dài hạn.',
        ],
        'expanding' => [
            'priority' => 'controlled_leverage',
            'narrative_hint' => 'Đòn bẩy trong tầm kiểm soát — mở rộng có ý thức, giữ cấu trúc.',
        ],
    ],

    'trajectory' => [
        'improving' => ['label' => 'Đang cải thiện', 'hint' => 'Dòng tiền đang cải thiện đều — cấu trúc đang chuyển sang giai đoạn tốt hơn.'],
        'stable' => ['label' => 'Ổn định', 'hint' => 'Cấu trúc ổn định.'],
        'deteriorating' => ['label' => 'Đang yếu dần', 'hint' => 'Bạn không gặp vấn đề ngay bây giờ, nhưng cấu trúc đang yếu dần — nên hành động sớm.'],
    ],

    'weakest_pillar_hints' => [
        'cashflow_integrity' => 'Cấu trúc đang tự bào mòn — cần cân bằng thu chi.',
        'liquidity_depth' => 'Vấn đề không nằm ở dòng tiền, mà ở độ dày lớp đệm.',
        'debt_load_quality' => 'Áp lực từ nợ đang làm yếu cấu trúc.',
        'structural_flexibility' => 'Rủi ro nằm ở sự phụ thuộc và biến động.',
    ],

    'normalization' => [
        'surplus_ratio_range' => [-0.3, 0.4],
        'runway_cap_months' => 12,
        'liquidity_expense_min_ratio' => 0,
        'liquidity_expense_good_ratio' => 6,
        'dti_safe_max' => 0.35,
        'dti_stress' => 0.6,
    ],
];
