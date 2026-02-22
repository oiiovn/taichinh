<?php

namespace App\Data;

use Carbon\Carbon;

/**
 * Định nghĩa 6 persona huấn luyện não: timeline 24 tháng, behavior logs, feedback, kỳ vọng học.
 * Số tiền VND. Tháng 1 = baseDate (24 tháng trước).
 */
final class PersonaTimelineDefinition
{
    public const PERSONA_KEYS = [
        'persona_1',  // Platform Dependent Creator
        'persona_2',  // Disciplined Accelerator
        'persona_3',  // Behavior Mismatch
        'persona_4',  // Debt Crisis + Concentration
        'persona_5',  // Volatile Income Entrepreneur
        'persona_6',  // Chronic Fragile
        'persona_7',  // Brain Evolution Test (6 phase)
    ];

    /** Thu/chi theo tháng (1-24). Đơn vị VND. */
    public static function getMonthlyTotals(string $personaKey): array
    {
        $def = self::definitions()[$personaKey] ?? null;
        if (! $def || empty($def['months'])) {
            return [];
        }
        $out = [];
        foreach ($def['months'] as $month => $row) {
            $out[$month] = [
                'income' => (int) ($row['income'] ?? 0),
                'expense' => (int) ($row['expense'] ?? 0),
                'income_merchant_group' => $row['income_merchant_group'] ?? null,
            ];
        }
        return $out;
    }

    /**
     * Thu theo nguồn (nhiều merchant_group) cho Diversification Cycle.
     * Trả về [ month => [ ['amount' => x, 'merchant_group' => 'y'], ... ] ].
     * Nếu rỗng → dùng getMonthlyTotals (một nguồn).
     */
    public static function getMonthlyIncomeBreakdown(string $personaKey): array
    {
        $def = self::definitions()[$personaKey] ?? null;
        if (! $def || empty($def['income_breakdown'])) {
            return [];
        }
        return $def['income_breakdown'];
    }

    /** Behavior logs: month => [{ suggestion_type, accepted, action_taken }]. */
    public static function getBehaviorLogs(string $personaKey): array
    {
        $def = self::definitions()[$personaKey] ?? null;
        return $def['behavior_logs'] ?? [];
    }

    /** Feedback: month => [{ feedback_type, reason_code }]. */
    public static function getFeedback(string $personaKey): array
    {
        $def = self::definitions()[$personaKey] ?? null;
        return $def['feedback'] ?? [];
    }

    /** Nợ: [ ['principal' => x, 'monthly_payment' => y, 'interest_rate' => z, 'name' => ... ], ... ]. Chỉ persona_4. */
    public static function getLiabilities(string $personaKey): ?array
    {
        $def = self::definitions()[$personaKey] ?? null;
        return $def['liabilities'] ?? null;
    }

    /** Kỳ vọng não học (ghi vào meta). */
    public static function getExpectedLearnings(string $personaKey): array
    {
        $def = self::definitions()[$personaKey] ?? null;
        return $def['expected_learnings'] ?? [];
    }

    /** Base date = đầu tháng 1 (24 tháng trước). */
    public static function getBaseDate(): Carbon
    {
        return Carbon::today()->subMonths(24)->startOfMonth();
    }

    public static function getSnapshotDateForCycle(int $cycle): Carbon
    {
        return self::getBaseDate()->copy()->addMonths($cycle - 1)->endOfMonth();
    }

    private static function definitions(): array
    {
        $m = 40_000_000;
        $m30 = 30_000_000;
        $m25 = 25_000_000;
        $m23 = 23_000_000;
        $m35 = 35_000_000;
        $m29 = 29_000_000;
        $m50 = 50_000_000;
        $m24 = 24_000_000;
        $m20 = 20_000_000;
        $m80 = 80_000_000;

        return [
            'persona_1' => [
                'label' => 'Platform Dependent Creator',
                'expected_learnings' => [
                    'platform_dependency cao → runway_weight tăng',
                    'forecast_error cao → conservative_bias tăng',
                    'diversification (cycle 9–16, 17–24) → platform_dependency giảm → mode platform_risk_alert → stable_growth',
                ],
                'months' => self::persona1Months($m, $m30, $m24, $m50),
                'income_breakdown' => self::persona1IncomeBreakdown($m, $m24, $m50),
                'behavior_logs' => self::persona1Behavior(),
                'feedback' => [],
                'liabilities' => null,
            ],
            'persona_2' => [
                'label' => 'Disciplined Accelerator',
                'expected_learnings' => [
                    'expense_suggestion_soften = 0',
                    'aggression có thể tăng',
                    'surplus_retention_pct giảm dần',
                    'mode chuyển StableGrowth → DisciplinedAccelerator',
                ],
                'months' => self::persona2Months($m30, $m23),
                'behavior_logs' => self::persona2Behavior(),
                'feedback' => [],
                'liabilities' => null,
            ],
            'persona_3' => [
                'label' => 'Behavior Mismatch',
                'expected_learnings' => [
                    'expense_suggestion_soften bật',
                    'aggression giảm',
                    'mode chuyển BehaviorMismatchWarning',
                    'threshold crisis nhạy hơn',
                ],
                'months' => self::persona3Months($m25, 24_500_000),
                'behavior_logs' => self::persona3Behavior(),
                'feedback' => self::persona3Feedback(),
                'liabilities' => null,
            ],
            'persona_4' => [
                'label' => 'Debt Crisis + Concentration',
                'expected_learnings' => [
                    'concentration → debt_urgency_boost tăng',
                    'crisis_threshold giảm',
                    'mode chuyển CrisisDirective',
                    'runway_weight tăng',
                ],
                'months' => self::persona4Months($m * 1, $m35),
                'behavior_logs' => [],
                'feedback' => [],
                'liabilities' => [
                    ['name' => 'NCC chính', 'principal' => 280_000_000, 'monthly_payment' => 18_000_000, 'interest_rate' => 18, 'weight_pct' => 70],
                    ['name' => 'Nợ phụ', 'principal' => 120_000_000, 'monthly_payment' => 6_000_000, 'interest_rate' => 12, 'weight_pct' => 30],
                ],
            ],
            'persona_5' => [
                'label' => 'Volatile Income Entrepreneur',
                'expected_learnings' => [
                    'volatility → runway_weight cao',
                    'forecast_error cao → conservative_bias tăng',
                    'mode chuyển FragileCoaching thay vì Stable',
                ],
                'months' => self::persona5Months($m20, $m80, $m35),
                'behavior_logs' => [],
                'feedback' => [],
                'liabilities' => null,
            ],
            'persona_6' => [
                'label' => 'Chronic Fragile',
                'expected_learnings' => [
                    'repeated_low_buffer → mode chuyển FragileCoaching',
                    'aggression tăng nhẹ',
                    'surplus_retention_pct tăng',
                ],
                'months' => self::persona6Months($m30, $m29),
                'behavior_logs' => self::persona6Behavior(),
                'feedback' => [],
                'liabilities' => null,
            ],
            'persona_7' => [
                'label' => 'Shockwave Founder',
                'expected_learnings' => [
                    'Pha 1 (1–4): platform_risk_alert, platform_dependency ~0.9',
                    'Pha 2 (5–7): fragile_coaching, DSI tăng, margin gần 0',
                    'Pha 3 (8–9): crisis_directive, forecast_error > 0.3 → conservative_bias',
                    'Pha 4 (10–12): behavior_mismatch_warning, expense_soften',
                    'Pha 5 (13–18): disciplined_accelerator, compliance cao',
                    'Pha 6 (19–24): stable_growth, đa nguồn thu',
                ],
                'months' => self::persona7Months(),
                'income_breakdown' => self::persona7IncomeBreakdown(),
                'behavior_logs' => self::persona7Behavior(),
                'feedback' => self::persona7Feedback(),
                'liabilities' => self::persona7Liabilities(),
            ],
        ];
    }

    private static function persona1Months(int $income40, int $expense30, int $income24, int $income50): array
    {
        $months = [];
        for ($m = 1; $m <= 6; $m++) {
            $months[$m] = ['income' => $income40, 'expense' => $expense30, 'income_merchant_group' => 'shopeefood'];
        }
        for ($m = 7; $m <= 9; $m++) {
            $months[$m] = ['income' => $income24, 'expense' => $expense30, 'income_merchant_group' => 'shopeefood'];
        }
        for ($m = 10; $m <= 15; $m++) {
            $months[$m] = ['income' => $income50, 'expense' => $expense30, 'income_merchant_group' => 'shopeefood'];
        }
        for ($m = 16; $m <= 24; $m++) {
            $income = $m === 20 ? 0 : $income50;
            $months[$m] = ['income' => $income, 'expense' => $expense30, 'income_merchant_group' => 'shopeefood'];
        }
        return $months;
    }

    /** Diversification Cycle: 1–8 một nguồn, 9–16 hai nguồn, 17–24 ba nguồn → platform_dependency giảm → mode có thể chuyển stable_growth. */
    private static function persona1IncomeBreakdown(int $income40, int $income24, int $income50): array
    {
        $out = [];
        for ($m = 1; $m <= 8; $m++) {
            $total = $m <= 6 ? $income40 : $income24;
            $out[$m] = [['amount' => $total, 'merchant_group' => 'shopeefood']];
        }
        for ($m = 9; $m <= 16; $m++) {
            $out[$m] = [
                ['amount' => (int) round($income50 * 0.6), 'merchant_group' => 'shopeefood'],
                ['amount' => (int) round($income50 * 0.4), 'merchant_group' => 'grab_food'],
            ];
        }
        for ($m = 17; $m <= 24; $m++) {
            if ($m === 20) {
                $out[$m] = [];
                continue;
            }
            $out[$m] = [
                ['amount' => (int) round($income50 * 0.4), 'merchant_group' => 'shopeefood'],
                ['amount' => (int) round($income50 * 0.3), 'merchant_group' => 'grab_food'],
                ['amount' => (int) round($income50 * 0.3), 'merchant_group' => 'facebook'],
            ];
        }
        return $out;
    }

    private static function persona1Behavior(): array
    {
        $logs = [];
        for ($m = 3; $m <= 24; $m += 3) {
            $logs[] = ['month' => $m, 'suggestion_type' => 'reduce_expense', 'accepted' => true, 'action_taken' => true];
        }
        return $logs;
    }

    private static function persona2Months(int $income, int $expense): array
    {
        $months = [];
        for ($m = 1; $m <= 24; $m++) {
            $months[$m] = ['income' => $income, 'expense' => $expense];
        }
        return $months;
    }

    private static function persona2Behavior(): array
    {
        $logs = [];
        for ($m = 1; $m <= 24; $m++) {
            $logs[] = ['month' => $m, 'suggestion_type' => 'reduce_expense', 'accepted' => true, 'action_taken' => true];
        }
        return $logs;
    }

    private static function persona3Months(int $income, int $expense): array
    {
        $months = [];
        for ($m = 1; $m <= 24; $m++) {
            $months[$m] = ['income' => $income, 'expense' => $expense];
        }
        return $months;
    }

    private static function persona3Behavior(): array
    {
        $logs = [];
        for ($m = 1; $m <= 24; $m++) {
            $logs[] = ['month' => $m, 'suggestion_type' => 'reduce_expense', 'accepted' => true, 'action_taken' => false];
        }
        return $logs;
    }

    private static function persona3Feedback(): array
    {
        $feedback = [];
        for ($m = 19; $m <= 24; $m++) {
            $feedback[] = ['month' => $m, 'feedback_type' => 'infeasible', 'reason_code' => 'cannot_reduce_expense'];
        }
        return $feedback;
    }

    private static function persona4Months(int $income, int $expense): array
    {
        $months = [];
        for ($m = 1; $m <= 24; $m++) {
            $months[$m] = ['income' => $income, 'expense' => $expense];
        }
        return $months;
    }

    private static function persona5Months(int $incomeLow, int $incomeHigh, int $expense): array
    {
        $months = [];
        $high = [1, 2, 3, 4, 8, 9, 10, 11, 12, 16, 17, 18, 19, 20, 24];
        for ($m = 1; $m <= 24; $m++) {
            $income = in_array($m, $high, true) ? $incomeHigh : $incomeLow;
            $months[$m] = ['income' => $income, 'expense' => $expense];
        }
        return $months;
    }

    private static function persona6Months(int $income, int $expense): array
    {
        $months = [];
        for ($m = 1; $m <= 24; $m++) {
            $months[$m] = ['income' => $income, 'expense' => $expense];
        }
        return $months;
    }

    private static function persona6Behavior(): array
    {
        $logs = [];
        for ($m = 6; $m <= 24; $m += 6) {
            $logs[] = ['month' => $m, 'suggestion_type' => 'reduce_expense', 'accepted' => true, 'action_taken' => true];
        }
        return $logs;
    }

    /** Shockwave Founder: Pha 1 ổn định giả, 2 mở rộng nợ, 3 shock thu, 4 chống đối, 5 học bài, 6 đa nguồn. */
    private static function persona7Months(): array
    {
        $months = [];
        for ($m = 1; $m <= 4; $m++) {
            $months[$m] = ['income' => 60_000_000, 'expense' => 45_000_000, 'income_merchant_group' => 'shopeefood'];
        }
        for ($m = 5; $m <= 7; $m++) {
            $months[$m] = ['income' => 60_000_000, 'expense' => 70_000_000, 'income_merchant_group' => 'shopeefood'];
        }
        for ($m = 8; $m <= 9; $m++) {
            $months[$m] = ['income' => 35_000_000, 'expense' => 70_000_000, 'income_merchant_group' => 'shopeefood'];
        }
        for ($m = 10; $m <= 12; $m++) {
            $months[$m] = ['income' => 35_000_000, 'expense' => 70_000_000, 'income_merchant_group' => 'shopeefood'];
        }
        $incomeP5 = [35, 38, 45, 50, 55, 65];
        $expenseP5 = [70, 65, 60, 58, 55, 55];
        for ($m = 13; $m <= 18; $m++) {
            $i = $m - 13;
            $months[$m] = ['income' => $incomeP5[$i] * 1_000_000, 'expense' => $expenseP5[$i] * 1_000_000, 'income_merchant_group' => 'shopeefood'];
        }
        // Pha 6 (19–24): thặng dư vừa phải → tone calm; compliance cao (13–24 accepted) → score >= 70 → disciplined_accelerator tại 2026-02-20
        for ($m = 19; $m <= 24; $m++) {
            $months[$m] = ['income' => 63_000_000, 'expense' => 55_000_000];
        }
        return $months;
    }

    /** Pha 1–4: 95% ShopeeFood; Pha 5: tăng dần; Pha 6: 50% ShopeeFood + Facebook + offline + sỉ. */
    private static function persona7IncomeBreakdown(): array
    {
        $out = [];
        for ($m = 1; $m <= 4; $m++) {
            $out[$m] = [['amount' => 57_000_000, 'merchant_group' => 'shopeefood'], ['amount' => 3_000_000, 'merchant_group' => 'grab_food']];
        }
        for ($m = 5; $m <= 12; $m++) {
            $inc = $m >= 8 ? 35_000_000 : 60_000_000;
            $out[$m] = [['amount' => $inc, 'merchant_group' => 'shopeefood']];
        }
        $incP5 = [35, 38, 45, 50, 55, 65];
        for ($m = 13; $m <= 18; $m++) {
            $out[$m] = [['amount' => $incP5[$m - 13] * 1_000_000, 'merchant_group' => 'shopeefood']];
        }
        for ($m = 19; $m <= 24; $m++) {
            $out[$m] = [
                ['amount' => 32_000_000, 'merchant_group' => 'shopeefood'],
                ['amount' => 16_000_000, 'merchant_group' => 'facebook'],
                ['amount' => 15_000_000, 'merchant_group' => 'offline'],
            ];
        }
        return $out;
    }

    /** Pha 4 (10–12): accepted = false; Pha 5–6: compliance cao. */
    private static function persona7Behavior(): array
    {
        $logs = [];
        for ($m = 1; $m <= 9; $m++) {
            $logs[] = ['month' => $m, 'suggestion_type' => 'reduce_expense', 'accepted' => true, 'action_taken' => true];
        }
        for ($m = 10; $m <= 12; $m++) {
            $logs[] = ['month' => $m, 'suggestion_type' => 'reduce_expense', 'accepted' => false, 'action_taken' => false];
        }
        for ($m = 13; $m <= 24; $m++) {
            $logs[] = ['month' => $m, 'suggestion_type' => 'reduce_expense', 'accepted' => true, 'action_taken' => true];
        }
        return $logs;
    }

    /** Pha 4: "Không khả thi", "Không đúng tình huống" 3 tháng. */
    private static function persona7Feedback(): array
    {
        $feedback = [];
        for ($m = 10; $m <= 12; $m++) {
            $feedback[] = ['month' => $m, 'feedback_type' => 'infeasible', 'reason_code' => 'wrong_situation'];
        }
        return $feedback;
    }

    /** 2 khoản: 1 lãi cao 200tr 18%, 1 đáo hạn gần; bắt đầu Pha 2 (tháng 5). */
    private static function persona7Liabilities(): array
    {
        return [
            ['name' => 'Vay thiết bị + mặt bằng', 'principal' => 200_000_000, 'monthly_payment' => 12_000_000, 'interest_rate' => 18, 'start_month' => 5],
            ['name' => 'Nợ đáo hạn gần', 'principal' => 80_000_000, 'monthly_payment' => 5_000_000, 'interest_rate' => 12, 'start_month' => 5],
        ];
    }
}
