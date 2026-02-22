<?php

/**
 * 3 dataset mẫu để test "trí tuệ" tài chính cá nhân (AdaptiveIncomeEngine, shock, debt spiral, tone).
 * Mỗi dataset = 6 tháng giao dịch thu (IN) / chi (OUT) theo tháng.
 * Chạy: php artisan db:seed --class=FinancialIntelligenceDatasetSeeder
 */

return [
    /*
    | Dataset 1 – Người đi làm ổn định
    | Thu cố định, chi tương đối ổn, 1–2 tháng có spike, 1 khoản vay nhỏ.
    | Test: Accumulation phase, Advisory tone, Liquidity framing, Materiality logic.
    */
    'dataset_1_stable_salaried' => [
        'name' => 'Người đi làm ổn định',
        'description' => 'Thu cố định mỗi tháng, chi ổn, 1-2 spike, 1 khoản vay nhỏ. Test: accumulation, advisory, liquidity, materiality.',
        'months' => 6,
        'income_per_month' => [
            1 => 25_000_000,
            2 => 25_500_000,
            3 => 25_000_000,
            4 => 24_800_000,
            5 => 25_200_000,
            6 => 25_000_000,
        ],
        'expense_per_month' => [
            1 => 18_000_000,
            2 => 18_500_000,
            3 => 22_000_000, // spike (mua sắm / dịch vụ)
            4 => 17_800_000,
            5 => 19_000_000, // spike nhẹ
            6 => 18_200_000,
        ],
        'debt_payment_per_month' => 2_000_000, // trả vay nhỏ
        'loan_small' => true,
    ],

    /*
    | Dataset 2 – Người thu nhập dao động mạnh
    | 3 tháng cao, 1 tháng gần 0, 1 tháng spike lớn, chi không đổi.
    | Test: Shock detection, Income stability score, Crisis framing, Contextual downgrade.
    */
    'dataset_2_volatile_income' => [
        'name' => 'Thu nhập dao động mạnh',
        'description' => '3 tháng cao, 1 tháng gần 0, 1 spike lớn, chi cố định. Test: shock, stability score, crisis framing.',
        'months' => 6,
        'income_per_month' => [
            1 => 35_000_000,
            2 => 36_000_000,
            3 => 34_000_000,
            4 => 500_000,   // gần như 0
            5 => 80_000_000, // spike lớn
            6 => 30_000_000,
        ],
        'expense_per_month' => [
            1 => 20_000_000,
            2 => 20_000_000,
            3 => 20_000_000,
            4 => 20_000_000,
            5 => 20_000_000,
            6 => 20_000_000,
        ],
        'debt_payment_per_month' => 0,
        'loan_small' => false,
    ],

    /*
    | Dataset 3 – Người nợ lớn, dòng tiền sát mép
    | Thu ~ chi, debt 35–45% thu, liquidity 2–3 tháng.
    | Test: Debt spiral risk, Priority resolver, Objective shift, Tone: Warning vs Crisis.
    */
    'dataset_3_high_debt_tight_cashflow' => [
        'name' => 'Nợ lớn, dòng tiền sát mép',
        'description' => 'Thu ~ chi, trả nợ 35-45% thu, thanh khoản 2-3 tháng. Test: debt spiral, priority, tone warning/crisis.',
        'months' => 6,
        'income_per_month' => [
            1 => 22_000_000,
            2 => 21_500_000,
            3 => 22_500_000,
            4 => 21_000_000,
            5 => 22_000_000,
            6 => 21_800_000,
        ],
        'expense_per_month' => [
            1 => 14_000_000, // chi thường (không tính trả nợ)
            2 => 13_500_000,
            3 => 14_200_000,
            4 => 13_800_000,
            5 => 14_000_000,
            6 => 13_600_000,
        ],
        'debt_payment_per_month' => 9_000_000, // ~40% thu
        'loan_small' => false,
        'liquidity_months' => 2.5,
    ],
];
