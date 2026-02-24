<?php

namespace App\Services;

/**
 * Reality Context Layer: lọc gợi ý theo ngữ cảnh đời sống.
 * Đầu vào: decision_space (pure arithmetic). Đầu ra: life_context + improvement_options đã qua
 * meaningful_threshold, suggestion_mode, recommended_focus.
 */
class ContextualAdjustmentService
{
    /** Internal scale (VND/tháng) khi không có median dân số */
    public const BRACKET_LOW_MAX = 200_000;

    public const BRACKET_MID_MAX = 1_000_000;

    /** meaningful_threshold floor (VND) */
    public const MEANINGFUL_FLOOR = 300_000;

    /** 3% thu nhập */
    public const INCOME_PCT_FOR_THRESHOLD = 0.03;

    /** 10% surplus_gap (khi thâm hụt) */
    public const SURPLUS_GAP_PCT = 0.10;

    public const BRACKET_LOW = 'low';

    public const BRACKET_MID = 'mid';

    public const BRACKET_HIGH = 'high';

    public const LEVEL_MICRO = 'micro';

    public const LEVEL_TACTICAL = 'tactical';

    public const LEVEL_STRUCTURAL = 'structural';

    public const MODE_NORMAL = 'normal';

    public const MODE_STRUCTURAL_REQUIRED = 'structural_required';

    /**
     * Điều chỉnh decision space theo ngữ cảnh đời sống.
     *
     * @param  array<string, mixed>  $decisionSpace  Raw từ buildDecisionSpace()
     * @param  array<string, mixed>  $sources  projection['sources']
     * @param  array<string, mixed>  $canonical  canonical metrics
     * @return array{life_context: array, suggestion_mode: string, micro_adjustments_hidden: bool, recommended_focus: string|null, meaningful_threshold: int, improvement_options: array, levels: array, decision_space: array}
     */
    public function adjust(array $decisionSpace, array $sources, array $canonical): array
    {
        $thu = (float) ($sources['projected_income'] ?? $sources['recurring_income'] ?? 0);
        $chi = (float) ($sources['behavior_expense'] ?? 0) + (float) ($sources['recurring_expense'] ?? 0);
        $debtService = (float) ($canonical['debt_service'] ?? $sources['debt_service_monthly'] ?? 0);
        $surplus = $thu - $chi - $debtService;
        $surplusGap = $surplus < 0 ? (float) abs($surplus) : 0.0;

        $lifeContext = $this->buildLifeContext((int) round($thu), (int) round($chi), (int) round($surplus), (float) $surplusGap, $sources, $thu);
        $meaningfulThreshold = $this->computeMeaningfulThreshold((int) round($thu), (int) round($surplusGap));

        $savings5pct = (int) ($decisionSpace['reduce_expense_5pct']['savings_per_month_vnd'] ?? 0);
        $extra10pct = (int) ($decisionSpace['increase_income_10pct']['extra_per_month_vnd'] ?? 0);

        $levelReduce = $this->classifyImpact($savings5pct, $meaningfulThreshold);
        $levelIncome = $this->classifyImpact($extra10pct, $meaningfulThreshold);

        $levels = [
            'reduce_expense_5pct' => $levelReduce,
            'increase_income_10pct' => $levelIncome,
            'hold_structure' => self::LEVEL_TACTICAL,
        ];

        $microReduce = $levelReduce === self::LEVEL_MICRO;
        $microIncome = $levelIncome === self::LEVEL_MICRO;
        $bothMicro = $microReduce && $microIncome;

        $suggestionMode = $bothMicro ? self::MODE_STRUCTURAL_REQUIRED : self::MODE_NORMAL;
        $microAdjustmentsHidden = $bothMicro;
        $recommendedFocus = $bothMicro ? 'income_scaling_or_cost_structure_review' : null;

        $improvementOptions = $this->buildFilteredOptions(
            $decisionSpace,
            $levels,
            $meaningfulThreshold,
            $microAdjustmentsHidden
        );

        $decisionSpaceWithReality = array_merge($decisionSpace, [
            'reality_filter' => [
                'suggestion_mode' => $suggestionMode,
                'micro_adjustments_hidden' => $microAdjustmentsHidden,
                'recommended_focus' => $recommendedFocus,
                'meaningful_threshold_vnd' => $meaningfulThreshold,
                'improvement_options' => $improvementOptions,
                'levels' => $levels,
            ],
        ]);

        return [
            'life_context' => $lifeContext,
            'suggestion_mode' => $suggestionMode,
            'micro_adjustments_hidden' => $microAdjustmentsHidden,
            'recommended_focus' => $recommendedFocus,
            'meaningful_threshold' => $meaningfulThreshold,
            'improvement_options' => $improvementOptions,
            'levels' => $levels,
            'decision_space' => $decisionSpaceWithReality,
        ];
    }

    /**
     * @return array{income_bracket: string, monthly_income: int, monthly_expense: int, surplus: int, surplus_gap: float, living_pressure_score: float, income_growth_difficulty: string}
     */
    private function buildLifeContext(int $monthlyIncome, int $monthlyExpense, int $surplus, float $surplusGap, array $sources): array
    {
        $incomeBracket = $this->resolveIncomeBracket($monthlyIncome);
        $livingPressureScore = $this->computeLivingPressureScore($monthlyIncome, $monthlyExpense, $surplus, $sources);
        $incomeGrowthDifficulty = $this->resolveIncomeGrowthDifficulty($incomeBracket, $sources);

        return [
            'income_bracket' => $incomeBracket,
            'monthly_income' => $monthlyIncome,
            'monthly_expense' => $monthlyExpense,
            'surplus' => $surplus,
            'surplus_gap' => (float) round($surplusGap, 0),
            'living_pressure_score' => round($livingPressureScore, 3),
            'income_growth_difficulty' => $incomeGrowthDifficulty,
        ];
    }

    /** Effort-to-Impact: với thu thấp/volatility cao, tăng thu 10% thường khó hơn. */
    private function resolveIncomeGrowthDifficulty(string $incomeBracket, array $sources): string
    {
        $volatility = (float) ($sources['volatility_ratio'] ?? $sources['income_volatility_ratio'] ?? 0);
        if ($incomeBracket === self::BRACKET_LOW || $volatility > 0.25) {
            return 'high';
        }
        if ($incomeBracket === self::BRACKET_MID || $volatility > 0.1) {
            return 'medium';
        }

        return 'low';
    }

    private function resolveIncomeBracket(int $monthlyIncome): string
    {
        if ($monthlyIncome < self::BRACKET_LOW_MAX) {
            return self::BRACKET_LOW;
        }
        if ($monthlyIncome < self::BRACKET_MID_MAX) {
            return self::BRACKET_MID;
        }

        return self::BRACKET_HIGH;
    }

    /**
     * meaningful_threshold = max(3% income, 300_000, 10% surplus_gap).
     */
    private function computeMeaningfulThreshold(int $monthlyIncome, float $surplusGap): int
    {
        $fromIncome = (int) round($monthlyIncome * self::INCOME_PCT_FOR_THRESHOLD);
        $fromGap = $surplusGap > 0 ? (int) round($surplusGap * self::SURPLUS_GAP_PCT) : 0;

        return (int) max(self::MEANINGFUL_FLOOR, $fromIncome, $fromGap);
    }

    private function classifyImpact(int $impactVnd, int $meaningfulThreshold): string
    {
        if ($impactVnd <= 0) {
            return self::LEVEL_MICRO;
        }
        if ($impactVnd < $meaningfulThreshold) {
            return self::LEVEL_MICRO;
        }
        if ($impactVnd < $meaningfulThreshold * 3) {
            return self::LEVEL_TACTICAL;
        }

        return self::LEVEL_STRUCTURAL;
    }

    /**
     * Chỉ số áp lực sống (0–1): thu thấp, chi cao, surplus âm → cao.
     */
    private function computeLivingPressureScore(int $income, int $expense, int $surplus, array $sources): float
    {
        if ($income <= 0) {
            return 1.0;
        }
        $ratio = $expense / $income;
        $surplusPressure = $surplus < 0 ? min(1.0, abs($surplus) / max(1, $income)) : 0.0;

        return (float) min(1.0, $ratio * 0.5 + $surplusPressure * 0.5);
    }

    /**
     * Trả improvement_options: khi micro_adjustments_hidden thì không đưa reduce/increase dạng micro vào như "chiến lược".
     */
    private function buildFilteredOptions(
        array $decisionSpace,
        array $levels,
        int $meaningfulThreshold,
        bool $microAdjustmentsHidden
    ): array {
        $out = [];

        foreach (['reduce_expense_5pct', 'increase_income_10pct', 'hold_structure'] as $key) {
            $opt = $decisionSpace[$key] ?? null;
            if (! is_array($opt)) {
                continue;
            }
            $level = $levels[$key] ?? self::LEVEL_TACTICAL;
            $impactVnd = (int) ($opt['savings_per_month_vnd'] ?? $opt['extra_per_month_vnd'] ?? 0);
            $isMicro = $level === self::LEVEL_MICRO;

            if ($microAdjustmentsHidden && $isMicro && $key !== 'hold_structure') {
                continue;
            }

            $out[] = [
                'key' => $key,
                'impact' => $opt['impact'] ?? '',
                'impact_vnd' => $impactVnd,
                'level' => $level,
                'mark_as_micro' => $isMicro,
                'meaningful_threshold' => $meaningfulThreshold,
            ];
        }

        return $out;
    }
}
