<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Decision Engine: đề xuất tối ưu, survival mode, minimal viable adjustment.
 */
class CashflowOptimizationService
{
    private const STEP_PCT = 5;
    private const STEP_INCOME = 1_000_000;
    private const MAX_EXTRA_INCOME = 100_000_000;

    /** Giới hạn thực tế: không đề xuất giảm chi > 60%. */
    private const MAX_EXPENSE_REDUCTION_PCT = 60;

    public function __construct(
        protected CashflowProjectionService $projection,
        protected FinancialConsistencyValidator $consistencyValidator,
        protected UserStrategyProfileService $strategyProfileService
    ) {}

    /**
     * @return array{
     *   survival_mode: bool,
     *   min_expense_reduction_pct: float|null,
     *   min_extra_income_per_month: float|null,
     *   months_to_break_even: int|null,
     *   suggested_loan: array{name: string, id: int|null}|null,
     *   insights: string[],
     *   summary: string|null,
     *   min_monthly_adjustment_vnd: float|null,
     *   survival_mode_reason: 'no_income'|'high_expense'|null
     * }
     */
    private const THU_THRESHOLD_PCT = 0.10; // thu < 10% tổng ra = coi như không có thu

    /**
     * @param  array<string, mixed>  $strategyProfile  Profile từ UserStrategyProfileService (behavior memory) — feedback-driven policy
     * @param  array{key: string, label: string, description: string}|null  $financialState  Tầng cấu trúc: trạng thái tài chính để gắn đề xuất với chiến lược
     * @param  array{key: string, label: string, description: string}|null  $priorityMode  Chế độ vận hành: crisis | defensive | optimization | growth
     * @param  array{tone: string, label: string, rationale: string, drivers: array}|null  $contextualFrame  Tone (calm|advisory|warning|crisis) từ Contextual Framing
     * @param  array{key: string, label: string, description: string}|null  $objective  Mục tiêu suy luận: debt_repayment|accumulation|investment|safety
     * @param  array{execution_consistency_score_reduce_expense?: float, execution_consistency_score_debt?: float, execution_consistency_score_income?: float, execution_consistency_score?: float}  $behavioralScores  Để điều chỉnh đề xuất: reduce_expense < 40% → cap giảm chi; compliance cao → aggressive
     */
    public function compute(
        int $userId,
        Collection $oweItems,
        Collection $receiveItems,
        array $position,
        int $months = 12,
        array $strategyProfile = [],
        ?array $financialState = null,
        ?array $priorityMode = null,
        ?array $contextualFrame = null,
        ?array $objective = null,
        array $behavioralScores = []
    ): array {
        $baseline = $this->projection->run($userId, $oweItems, $receiveItems, $position, $months, []);
        $timeline = $baseline['timeline'] ?? [];
        $minBalance = $timeline ? min(array_column($timeline, 'so_du_cuoi')) : 0;
        $hasNegative = $minBalance < 0;
        $riskScore = $baseline['risk_score'] ?? 'stable';
        $modeKey = $priorityMode['key'] ?? null;
        $survivalMode = in_array($riskScore, [CashflowProjectionService::RISK_DANGER, CashflowProjectionService::RISK_CRITICAL], true) && $hasNegative;
        if ($priorityMode !== null) {
            if ($modeKey === 'crisis') {
                $survivalMode = true;
            }
            if ($modeKey === 'defensive') {
                $survivalMode = false;
            }
        }

        $sources = $baseline['sources'] ?? [];
        $chi = ($sources['behavior_expense'] ?? 0) + ($sources['recurring_expense'] ?? 0);
        $thu = (float) ($sources['projected_income'] ?? $sources['recurring_income'] ?? 0);
        $debtTotal = (float) ($sources['loan_schedule'] ?? 0);
        $outflowPerMonth = $chi + ($debtTotal / max(1, $months));
        $survivalModeReason = null;
        if ($survivalMode && $outflowPerMonth > 0) {
            $survivalModeReason = ($thu < self::THU_THRESHOLD_PCT * $outflowPerMonth) ? 'no_income' : 'high_expense';
        }

        $minExpensePct = null;
        $minExtraIncome = null;
        for ($pct = 0; $pct <= 100; $pct += self::STEP_PCT) {
            $r = $this->projection->run($userId, $oweItems, $receiveItems, $position, $months, ['expense_reduction_pct' => $pct]);
            $min = $r['timeline'] ? min(array_column($r['timeline'], 'so_du_cuoi')) : 0;
            if ($min >= 0) {
                $minExpensePct = min($pct, self::MAX_EXPENSE_REDUCTION_PCT);
                break;
            }
        }
        if ($minExpensePct !== null && $minExpensePct > self::MAX_EXPENSE_REDUCTION_PCT) {
            $minExpensePct = self::MAX_EXPENSE_REDUCTION_PCT;
        }
        if ($minExpensePct === null && $hasNegative) {
            for ($inc = self::STEP_INCOME; $inc <= self::MAX_EXTRA_INCOME; $inc += self::STEP_INCOME) {
                $r = $this->projection->run($userId, $oweItems, $receiveItems, $position, $months, ['extra_income_per_month' => $inc]);
                $min = $r['timeline'] ? min(array_column($r['timeline'], 'so_du_cuoi')) : 0;
                if ($min >= 0) {
                    $minExtraIncome = $inc;
                    break;
                }
            }
        }

        $reduceExpenseScore = (float) ($behavioralScores['execution_consistency_score_reduce_expense'] ?? 100);
        if ($reduceExpenseScore < 40 && $minExpensePct !== null && $minExpensePct > 10) {
            $minExpensePct = 10;
        }

        $monthsToBreakEven = null;
        foreach ($timeline as $i => $row) {
            if (($row['so_du_cuoi'] ?? 0) >= 0) {
                $monthsToBreakEven = $i + 1;
                break;
            }
        }

        $survivalHorizonMonths = null;
        foreach ($timeline as $i => $row) {
            if (($row['so_du_cuoi'] ?? 0) < 0) {
                $survivalHorizonMonths = $i;
                break;
            }
        }
        if ($survivalHorizonMonths === null && $timeline) {
            $survivalHorizonMonths = count($timeline);
        }
        $survivalHorizonMessage = $this->buildSurvivalHorizonMessage($survivalHorizonMonths, (bool) $hasNegative, $priorityMode);

        $optimalPlanMessage = null;
        $monthsToBreakEvenWithPlan = null;
        $monthsExpense = null;
        $monthsIncome = null;
        if ($minExpensePct !== null && $minExpensePct > 0) {
            $r = $this->projection->run($userId, $oweItems, $receiveItems, $position, $months, ['expense_reduction_pct' => $minExpensePct]);
            $t = $r['timeline'] ?? [];
            foreach ($t as $idx => $row) {
                if (($row['so_du_cuoi'] ?? 0) >= 0) {
                    $monthsExpense = $idx + 1;
                    break;
                }
            }
        }
        if ($minExtraIncome !== null && $minExtraIncome > 0) {
            $r = $this->projection->run($userId, $oweItems, $receiveItems, $position, $months, ['extra_income_per_month' => $minExtraIncome]);
            $t = $r['timeline'] ?? [];
            foreach ($t as $idx => $row) {
                if (($row['so_du_cuoi'] ?? 0) >= 0) {
                    $monthsIncome = $idx + 1;
                    break;
                }
            }
        }
        $monthsToBreakEvenWithPlan = $monthsExpense ?? $monthsIncome;

        $msgExpense = null;
        if ($minExpensePct !== null && $minExpensePct > 0) {
            $msgExpense = $monthsExpense !== null
                ? "Giảm " . (int) $minExpensePct . "% chi → thoát âm sau " . $monthsExpense . " tháng."
                : "Giảm " . (int) $minExpensePct . "% chi để cân bằng dòng tiền.";
        }
        $msgIncome = null;
        if ($minExtraIncome !== null && $minExtraIncome > 0) {
            $msgIncome = $monthsIncome !== null
                ? "Tăng thu " . number_format((int) $minExtraIncome) . " ₫/tháng → thoát âm sau " . $monthsIncome . " tháng."
                : "Tăng thu " . number_format((int) $minExtraIncome) . " ₫/tháng để cân bằng dòng tiền.";
        }

        $optimalPlanMessage = $this->pickOptimalPlanMessage(
            $financialState,
            $strategyProfile,
            $msgExpense,
            $msgIncome,
            $chi,
            $hasNegative
        );

        $suggestedLoan = $this->suggestLoanToPayFirst($oweItems);
        $monthlyShortfall = $outflowPerMonth - $thu;
        if ($monthlyShortfall < 0) {
            $monthlyShortfall = 0;
        }
        $insights = $this->buildInsights(
            $baseline,
            $minBalance,
            $minExpensePct,
            $minExtraIncome,
            $monthsToBreakEven,
            $suggestedLoan,
            $chi,
            $thu,
            $survivalMode,
            $survivalModeReason,
            $survivalHorizonMessage,
            $optimalPlanMessage,
            $financialState,
            $strategyProfile
        );
        $minAdjustmentVnd = null;
        if ($minExtraIncome !== null) {
            $minAdjustmentVnd = $minExtraIncome;
        } elseif ($minExpensePct !== null && $chi > 0) {
            $minAdjustmentVnd = $chi * $minExpensePct / 100;
        } elseif ($hasNegative) {
            $minAdjustmentVnd = $monthlyShortfall > 0 ? $monthlyShortfall : abs($minBalance) / max(1, $months);
        }

        $receivablePerMonth = $receiveItems->isEmpty() ? 0 : ($receiveItems->sum('outstanding') + $receiveItems->sum('unpaid_interest')) / max(1, $months);
        $effectiveIncome = $thu + $receivablePerMonth;
        $rootCauses = $this->computeRootCauses($sources, $oweItems, $receiveItems, $months, $outflowPerMonth, $effectiveIncome, $strategyProfile);
        $rootCauses = $this->filterRootCausesByState($rootCauses, $financialState, $sources, $months);
        $rootCauses = $this->consistencyValidator->sanitizeRootCauses($effectiveIncome, $minBalance, $rootCauses);

        $strategicGuidance = $this->buildStrategicGuidance($financialState, $hasNegative, $suggestedLoan, $objective, $priorityMode);

        return [
            'survival_mode' => $survivalMode,
            'survival_mode_reason' => $survivalModeReason,
            'min_expense_reduction_pct' => $minExpensePct,
            'min_extra_income_per_month' => $minExtraIncome,
            'months_to_break_even' => $monthsToBreakEven,
            'suggested_loan' => $suggestedLoan,
            'insights' => $insights,
            'summary' => $this->buildSummary($baseline, $chi, $thu, $minBalance, $survivalModeReason, $monthlyShortfall),
            'min_monthly_adjustment_vnd' => $minAdjustmentVnd,
            'root_causes' => $rootCauses,
            'survival_horizon_months' => $survivalHorizonMonths,
            'survival_horizon_message' => $survivalHorizonMessage,
            'optimal_plan_message' => $optimalPlanMessage,
            'months_to_break_even_with_plan' => $monthsToBreakEvenWithPlan,
            'financial_state' => $financialState,
            'strategic_guidance' => $strategicGuidance,
            'priority_mode' => $priorityMode,
            'contextual_frame' => $contextualFrame,
            'objective' => $objective,
        ];
    }

    /** Feedback-driven: user hay reject "tăng thu" → ưu tiên đề xuất giảm chi / tối ưu nợ. */
    private function preferExpenseOverIncome(array $profile): bool
    {
        return (float) ($profile['reject_income_solution_ratio'] ?? 0) >= 0.4;
    }

    /** Feedback-driven: user hay reject "giảm chi" → ưu tiên đề xuất tăng thu / tối ưu nợ. */
    private function preferIncomeOverExpense(array $profile): bool
    {
        return (float) ($profile['reject_expense_solution_ratio'] ?? 0) >= 0.4;
    }

    /**
     * Chọn thông điệp phương án tối ưu theo state + profile (chiến lược + hành vi).
     */
    private function pickOptimalPlanMessage(
        ?array $financialState,
        array $strategyProfile,
        ?string $msgExpense,
        ?string $msgIncome,
        float $chi,
        bool $hasNegative
    ): ?string {
        $stateKey = $financialState['key'] ?? null;
        $preferExpense = $this->preferExpenseOverIncome($strategyProfile);
        $preferIncome = $this->preferIncomeOverExpense($strategyProfile);

        if ($hasNegative) {
            if ($preferExpense && $msgExpense !== null) {
                return $msgExpense;
            }
            if ($preferIncome && $msgIncome !== null) {
                return $msgIncome;
            }
            if (in_array($stateKey, ['debt_spiral_risk', 'fragile_liquidity'], true) && $msgExpense !== null) {
                return $msgExpense;
            }
            return $msgExpense ?? $msgIncome;
        }

        if (in_array($stateKey, ['accumulation_phase', 'stable_conservative'], true)) {
            return null;
        }
        return $msgExpense ?? $msgIncome;
    }

    /** Hướng dẫn chiến lược theo state + objective (Tầng Chiến lược, align với mục tiêu). Crisis → directive sống sót. */
    private function buildStrategicGuidance(?array $financialState, bool $hasNegative, ?array $suggestedLoan, ?array $objective = null, ?array $priorityMode = null): array
    {
        $modeKey = $priorityMode['key'] ?? null;
        if ($modeKey === 'crisis') {
            return [
                'state' => $financialState,
                'guidance_lines' => [
                    'Trong 30 ngày tới, mục tiêu không phải tối ưu lãi — mục tiêu là sống sót.',
                    'Giữ lại tối thiểu 20–30 triệu tiền mặt.',
                    'Tạm dừng toàn bộ khoản chi không thiết yếu.',
                ],
            ];
        }

        $objKey = $objective['key'] ?? null;
        $guidance = [];

        if ($financialState !== null) {
            $key = $financialState['key'] ?? '';
            if ($key === 'accumulation_phase' && ! $hasNegative) {
                if ($objKey !== 'debt_repayment') {
                    $guidance[] = 'Bạn đang ở pha tích lũy — ưu tiên tối ưu nợ và mục tiêu dài hạn, không cần thắt chặt chi tiêu.';
                }
                if ($suggestedLoan && ($suggestedLoan['reason'] ?? '') === 'highest_interest') {
                    $guidance[] = $objKey === 'investment' ? 'Nợ 0% có thể giữ hoặc đầu tư thay vì trả sớm; ưu tiên trả lãi cao trước.' : 'Nên trả lãi cao trước; nợ 0% có thể giữ hoặc đầu tư thay vì trả sớm.';
                }
            } elseif ($key === 'leveraged_growth') {
                $guidance[] = 'Thu ổn, nợ cao nhưng DSCR ổn — có thể giữ cấu trúc, tối ưu lãi và kỳ hạn.';
            } elseif ($key === 'stable_conservative' && ! $hasNegative) {
                $guidance[] = $objKey === 'investment' ? 'Thu ổn, nợ thấp — phù hợp để cân nhắc đầu tư theo mục tiêu dài hạn.' : 'Thu ổn, nợ thấp — có thể cân nhắc đầu tư hoặc trả nợ sớm tùy lãi suất.';
            } elseif ($key === 'debt_spiral_risk') {
                $guidance[] = 'Ưu tiên tái cấu trúc nợ và thanh khoản trước khi tăng chi tiêu.';
            } elseif ($key === 'fragile_liquidity') {
                $guidance[] = $objKey === 'safety' ? 'Mục tiêu giữ an toàn phù hợp — tăng dự phòng trước khi tăng rủi ro.' : 'Tăng dự phòng trước khi tăng rủi ro.';
            }
        }

        if ($objKey === 'debt_repayment' && $suggestedLoan && ! empty($suggestedLoan['name'])) {
            array_unshift($guidance, 'Chiến lược align với mục tiêu trả nợ — ưu tiên khoản «' . $suggestedLoan['name'] . '» theo gợi ý.');
        }
        if ($objKey === 'safety' && empty($guidance)) {
            $guidance[] = 'Chiến lược theo hướng giữ an toàn — ưu tiên buffer và thanh khoản.';
        }
        if ($objKey === 'investment') {
            $guidance[] = 'Có thể phân bổ dư tiền vào đầu tư theo mức chấp nhận rủi ro.';
        }

        return ['state' => $financialState, 'guidance_lines' => array_slice($guidance, 0, 5)];
    }

    private function buildSurvivalHorizonMessage(?int $horizonMonths, bool $hasNegative, ?array $priorityMode = null): ?string
    {
        if (! $hasNegative) {
            return null;
        }
        $modeKey = $priorityMode['key'] ?? null;
        if ($modeKey === 'defensive' && $horizonMonths !== null && $horizonMonths >= 2) {
            return "Chế độ phòng thủ: còn runway khoảng {$horizonMonths} tháng. Ưu tiên bảo vệ buffer, tăng dự phòng — chưa phải khủng hoảng.";
        }
        if ($horizonMonths === null) {
            return null;
        }
        if ($horizonMonths === 0) {
            return 'Chế độ khủng hoảng: dòng tiền âm ngay tháng đầu. Cần hành động khẩn cấp.';
        }
        if ($modeKey === 'crisis') {
            return "Chế độ khủng hoảng: còn tối đa {$horizonMonths} tháng trước khi vỡ dòng tiền. Cần hành động ngay.";
        }
        return "Bạn còn runway {$horizonMonths} tháng nếu giữ nguyên cấu trúc. Sau đó sẽ vỡ dòng tiền.";
    }

    /**
     * Root cause phải phụ thuộc vào state — 1 narrative. accumulation/stable: bỏ no_income.
     * fragile_liquidity: ưu tiên cashflow + liquidity; giảm weight debt cause nếu debt chưa gây áp lực thực.
     *
     * @param  array<int, array{key: string, label: string}>  $causes
     * @param  array{key: string}|null  $resolvedState
     * @param  array<string, mixed>  $sources
     * @return array<int, array{key: string, label: string}>
     */
    private function filterRootCausesByState(array $causes, ?array $resolvedState, array $sources = [], int $months = 12): array
    {
        if ($resolvedState === null) {
            return $causes;
        }
        $stateKey = $resolvedState['key'] ?? '';
        if (in_array($stateKey, ['accumulation_phase', 'stable_conservative'], true)) {
            return array_values(array_filter($causes, fn ($c) => ($c['key'] ?? '') !== 'no_income'));
        }
        if ($stateKey === 'fragile_liquidity') {
            $outflow = ($sources['behavior_expense'] ?? 0) + ($sources['recurring_expense'] ?? 0) + ((float) ($sources['loan_schedule'] ?? 0) / max(1, $months));
            $debtPerMonth = $months > 0 ? (float) ($sources['loan_schedule'] ?? 0) / $months : 0;
            $debtPressureRatio = $outflow > 0 ? $debtPerMonth / $outflow : 0;
            $debtCausingPressure = $debtPressureRatio >= 0.35;
            $cashflowFirstKeys = ['no_income', 'unfavorable_recurring', 'high_spending'];
            $debtKeys = ['high_debt_payment', 'high_interest'];
            $cashflowCauses = array_values(array_filter($causes, fn ($c) => in_array($c['key'] ?? '', $cashflowFirstKeys, true)));
            $debtCauses = array_values(array_filter($causes, fn ($c) => in_array($c['key'] ?? '', $debtKeys, true)));
            $otherCauses = array_values(array_filter($causes, fn ($c) => ! in_array($c['key'] ?? '', array_merge($cashflowFirstKeys, $debtKeys), true)));
            if (! $debtCausingPressure) {
                $debtCauses = [];
            }
            return array_merge($cashflowCauses, $debtCauses, $otherCauses);
        }
        return $causes;
    }

    /**
     * Phân loại nguyên nhân thâm hụt (Root Cause Analysis). Có profile thì áp dụng dynamic weight (ẩn cause user hay reject).
     *
     * @param  array<string, mixed>  $strategyProfile
     * @return array<int, array{key: string, label: string}>
     */
    private function computeRootCauses(array $sources, Collection $oweItems, Collection $receiveItems, int $months, float $outflowPerMonth, float $thuPerMonth, array $strategyProfile = []): array
    {
        $recurringIncome = (float) ($sources['recurring_income'] ?? 0);
        $recurringExpense = (float) ($sources['recurring_expense'] ?? 0);
        $chi = ($sources['behavior_expense'] ?? 0) + $recurringExpense;
        $debtTotal = (float) ($sources['loan_schedule'] ?? 0);
        $debtPerMonth = $months > 0 ? $debtTotal / $months : 0;
        $causes = [];

        $incomeCoverage = $outflowPerMonth > 0 ? $thuPerMonth / $outflowPerMonth : 0;
        $noIncomeAbsolute = $thuPerMonth < 1_000_000 || ($outflowPerMonth > 0 && $incomeCoverage < 0.10);
        if ($outflowPerMonth > 0 && $noIncomeAbsolute) {
            $causes[] = ['key' => 'no_income', 'label' => 'Do không có thu nhập, hoặc thu nhập không đáng kể'];
        }
        if ($recurringExpense > $recurringIncome && ($recurringExpense > 0 || $recurringIncome > 0)) {
            $causes[] = ['key' => 'unfavorable_recurring', 'label' => 'Do recurring bất lợi (chi định kỳ > thu định kỳ)'];
        }
        if ($thuPerMonth > 0 && $chi > 1.3 * $thuPerMonth) {
            $causes[] = ['key' => 'high_spending', 'label' => 'Do chi tiêu cao'];
        }
        if ($outflowPerMonth > 0 && ($debtPerMonth > 0.3 * $outflowPerMonth || $debtPerMonth > 20_000_000)) {
            $causes[] = ['key' => 'high_debt_payment', 'label' => 'Do trả nợ lớn'];
        }

        $totalDebt = $oweItems->sum('outstanding') + $oweItems->sum('unpaid_interest');
        if ($totalDebt > 0) {
            $annualRate = function ($item) {
                $e = $item->entity ?? null;
                if (! $e) return 0.0;
                $r = (float) ($e->interest_rate ?? 0);
                $u = $e->interest_unit ?? 'yearly';
                return match ($u) { 'yearly' => $r, 'monthly' => $r * 12, 'daily' => $r * 365, default => $r };
            };
            $weightedRate = $oweItems->sum(fn ($i) => $annualRate($i) * ((float) $i->outstanding + (float) ($i->unpaid_interest ?? 0)));
            $avgRate = $weightedRate / $totalDebt;
            if ($avgRate > 12) {
                $causes[] = ['key' => 'high_interest', 'label' => 'Do lãi cao'];
            }
        }

        if ($strategyProfile !== [] && (int) ($strategyProfile['total_feedback_count'] ?? 0) >= 2) {
            $weights = $this->strategyProfileService->rootCauseWeights($strategyProfile);
            $minWeight = 0.35;
            $causes = array_values(array_filter($causes, function ($c) use ($weights, $minWeight) {
                $key = $c['key'] ?? '';
                if (! isset($weights[$key])) {
                    return true;
                }
                return $weights[$key] >= $minWeight;
            }));
        }

        return $causes;
    }

    /**
     * Gợi ý khoản nên trả trước: lãi cao nhất; nếu tất cả lãi = 0 thì ưu tiên kỳ hạn gần nhất hoặc số dư lớn nhất.
     *
     * @return array{name: string, id: int|null, reason: 'highest_interest'|'nearest_due'|'largest_balance'}|null
     */
    private function suggestLoanToPayFirst(Collection $oweItems): ?array
    {
        if ($oweItems->isEmpty()) {
            return null;
        }
        $annualRate = function ($item) {
            $e = $item->entity ?? null;
            if (! $e) return 0.0;
            $r = (float) ($e->interest_rate ?? 0);
            $u = $e->interest_unit ?? 'yearly';
            return match ($u) { 'yearly' => $r, 'monthly' => $r * 12, 'daily' => $r * 365, default => $r };
        };
        $hasAnyInterest = $oweItems->contains(fn ($item) => $annualRate($item) > 0);
        if ($hasAnyInterest) {
            $first = $oweItems->sortByDesc($annualRate)->first();
            return [
                'name' => $first->name,
                'id' => $first->source === 'linked' ? ($first->entity->id ?? null) : null,
                'reason' => 'highest_interest',
            ];
        }
        $withDue = $oweItems->filter(fn ($i) => isset($i->due_date) && $i->due_date !== null);
        if ($withDue->isNotEmpty()) {
            $first = $withDue->sortBy('due_date')->first();
            return [
                'name' => $first->name,
                'id' => $first->source === 'linked' ? ($first->entity->id ?? null) : null,
                'reason' => 'nearest_due',
            ];
        }
        $first = $oweItems->sortByDesc('outstanding')->first();
        return [
            'name' => $first->name,
            'id' => $first->source === 'linked' ? ($first->entity->id ?? null) : null,
            'reason' => 'largest_balance',
        ];
    }

    private function buildInsights(
        array $baseline,
        float $minBalance,
        ?float $minExpensePct,
        ?float $minExtraIncome,
        ?int $monthsToBreakEven,
        ?array $suggestedLoan,
        float $chi,
        float $thu,
        bool $survivalMode,
        ?string $survivalModeReason = null,
        ?string $survivalHorizonMessage = null,
        ?string $optimalPlanMessage = null,
        ?array $financialState = null,
        array $strategyProfile = []
    ): array {
        $insights = [];
        $timeline = $baseline['timeline'] ?? [];
        $lastBalance = $timeline ? ($timeline[count($timeline) - 1]['so_du_cuoi'] ?? 0) : 0;
        $preferExpense = $this->preferExpenseOverIncome($strategyProfile);
        $preferIncome = $this->preferIncomeOverExpense($strategyProfile);

        if ($survivalHorizonMessage !== null) {
            $insights[] = $survivalHorizonMessage;
        }
        if ($optimalPlanMessage !== null) {
            $insights[] = 'Phương án tối ưu nhất: ' . $optimalPlanMessage;
        }
        if ($chi > 0 && $thu > 0) {
            $pct = round($chi / $thu * 100);
            $insights[] = "Bạn đang chi tiêu " . number_format($chi) . " ₫/tháng, bằng khoảng {$pct}% so với thu định kỳ (" . number_format($thu) . " ₫/tháng).";
        }
        if ($minBalance < 0 && $timeline) {
            $insights[] = "Nếu không điều chỉnh, bạn sẽ âm tiền trong kỳ dự báo, thấp nhất khoảng " . number_format((int) abs($minBalance)) . " ₫.";
        }
        if ($lastBalance < 0 && $timeline) {
            $n = count($timeline);
            $insights[] = "Sau {$n} tháng, số dư dự kiến âm " . number_format((int) abs($lastBalance)) . " ₫.";
        }
        if ($minExpensePct !== null && $minExpensePct > 0 && ! $preferIncome && $survivalModeReason !== 'no_income') {
            $insights[] = "Giảm chi ít nhất " . (int) $minExpensePct . "% để không vỡ dòng tiền.";
        }
        if ($minExtraIncome !== null && $minExtraIncome > 0 && ! $preferExpense) {
            $insights[] = "Hoặc tăng thu ít nhất " . number_format((int) $minExtraIncome) . " ₫/tháng.";
        }
        if ($monthsToBreakEven !== null && $monthsToBreakEven > 0) {
            $insights[] = "Với mức hiện tại, bạn có thể thoát âm từ tháng thứ {$monthsToBreakEven}.";
        }
        if ($suggestedLoan && $suggestedLoan['name']) {
            $reason = $suggestedLoan['reason'] ?? 'highest_interest';
            if ($reason === 'highest_interest') {
                $insights[] = "Nên ưu tiên trả khoản «{$suggestedLoan['name']}» (lãi cao nhất) trước để giảm tổng lãi.";
            } elseif ($reason === 'nearest_due') {
                $insights[] = "Nên ưu tiên trả khoản «{$suggestedLoan['name']}» (kỳ hạn gần nhất) trước.";
            } else {
                $insights[] = "Nên ưu tiên trả khoản «{$suggestedLoan['name']}» (số dư lớn nhất) trước để giảm áp lực dòng tiền.";
            }
        }
        if ($survivalMode) {
            $need = $minExtraIncome ?? ($minExpensePct !== null && $chi > 0 ? round($chi * $minExpensePct / 100) : (int) abs($minBalance));
            if ($survivalModeReason === 'no_income') {
                $insights[] = "⚠ Chế độ sinh tồn: bạn đang không có nguồn thu (hoặc thu rất thấp). Với cấu trúc hiện tại, cần tối thiểu " . number_format($need) . " ₫/tháng dòng tiền vào để cân bằng.";
            } else {
                $insights[] = "⚠ Chế độ sinh tồn: cần giảm chi tối thiểu " . number_format($need) . " ₫/tháng hoặc tăng thu tương đương để không vỡ dòng tiền.";
            }
        }

        return array_slice($insights, 0, 5);
    }

    private function buildSummary(array $baseline, float $chi, float $thu, float $minBalance, ?string $survivalModeReason = null, float $monthlyShortfall = 0): ?string
    {
        if ($chi <= 0 && $thu <= 0) return null;
        if ($survivalModeReason === 'no_income') {
            $s = "Bạn đang không có nguồn thu (hoặc thu rất thấp). Với cấu trúc hiện tại, cần tối thiểu " . number_format((int) $monthlyShortfall) . " ₫/tháng dòng tiền vào để cân bằng.";
            return $s;
        }
        $pct = $thu > 0 ? round($chi / $thu * 100) : 0;
        $s = "Bạn đang chi " . number_format($chi) . " ₫/tháng";
        if ($thu > 0) {
            $s .= ", cao hơn thu " . ($pct > 100 ? ($pct - 100) . "%" : "khoảng {$pct}% thu") . ".";
        } else {
            $s .= ".";
        }
        if ($minBalance < 0) {
            $s .= " Nếu không điều chỉnh, bạn sẽ âm tới " . number_format((int) abs($minBalance)) . " ₫ trong kỳ dự báo.";
        }
        return $s;
    }
}
