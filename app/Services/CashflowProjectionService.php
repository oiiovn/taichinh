<?php

namespace App\Services;

use App\Models\LoanContract;
use App\Models\LoanPendingPayment;
use App\Models\TransactionHistory;
use App\Models\UserRecurringPattern;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CashflowProjectionService
{
    public const RISK_STABLE = 'stable';
    public const RISK_WARNING = 'warning';
    public const RISK_DANGER = 'danger';
    public const RISK_CRITICAL = 'critical';

    public const FLAG_NEGATIVE = 'negative';
    public const FLAG_RISK = 'risk';
    public const FLAG_SURPLUS = 'surplus';

    /** Ngưỡng (VNĐ): dưới đây = tháng rủi ro; trên = tháng dư mạnh */
    private const THRESHOLD_RISK = 5_000_000;
    private const THRESHOLD_SURPLUS = 20_000_000;

    /** Dư tối thiểu để coi cashflow “mạnh” → cap risk, không được Nguy hiểm/Cực cao */
    private const MIN_SURPLUS_CAP_RISK = 5_000_000;

    public function __construct(
        protected LoanLedgerService $ledgerService,
        protected AdaptiveIncomeEngine $adaptiveIncome,
        protected FinancialRiskScoringService $riskScoring,
        protected FinancialConsistencyValidator $consistencyValidator,
        protected ExpensePurityService $expensePurity
    ) {}

    /**
     * Chạy engine: timeline 3–6–12 tháng, cảnh báo, risk score.
     *
     * @param  array{extra_income_per_month?: float, extra_payment_loan_id?: int, extra_payment_amount?: float, expense_reduction_pct?: float}  $scenario
     * @param  array{economic_context?: array}|null  $runContext  economic_context từ EconomicContextService → buffer modifier
     * @return array{timeline: array, alert: string|null, risk_score: string, risk_label: string, risk_color: string, sources: array}
     */
    public function run(
        int $userId,
        Collection $oweItems,
        Collection $receiveItems,
        array $position,
        int $months = 12,
        array $scenario = [],
        array $runContext = []
    ): array {
        $linkedAccountNumbers = $runContext['linked_account_numbers'] ?? [];
        $incomeResult = $this->adaptiveIncome->estimate($userId, $linkedAccountNumbers);
        $projectedIncome = (float) ($incomeResult['projected_income_per_month'] ?? 0);
        $recurringExpense = $this->recurringExpensePerMonth($userId);
        $behaviorExpense = $this->behaviorExpensePerMonth($userId, $linkedAccountNumbers);
        $debtByMonth = $this->loanPaymentByMonth($userId, $oweItems, $months);
        $receivableByMonth = $this->receivableByMonth($receiveItems, $months);

        $extraIncome = (float) ($scenario['extra_income_per_month'] ?? 0);
        $expenseReductionPct = (float) ($scenario['expense_reduction_pct'] ?? 0) / 100;
        $extraPaymentLoanId = isset($scenario['extra_payment_loan_id']) ? (int) $scenario['extra_payment_loan_id'] : null;
        $extraPaymentAmount = isset($scenario['extra_payment_amount']) ? (float) $scenario['extra_payment_amount'] : 0;

        $liquidBalance = (float) ($position['liquid_balance'] ?? 0);
        $start = Carbon::now()->startOfMonth();
        $firstMonthKey = $start->format('Y-m');
        $debtFirstMonth = (float) ($debtByMonth[$firstMonthKey] ?? 0);
        $committedOutflows30d = $debtFirstMonth + $recurringExpense;
        $availableLiquidity = $liquidBalance - $committedOutflows30d;
        $lockedLiquidity = $committedOutflows30d;
        $effectiveLiquidity = $availableLiquidity;

        $useLiquidStart = config('financial_brain.liquidity.use_liquid_balance_as_start', true);
        $useAvailableStart = config('financial_brain.liquidity.use_available_liquidity_as_start', true);
        $soDuDau = $useLiquidStart
            ? ($useAvailableStart ? $availableLiquidity : $liquidBalance)
            : 0;

        $timeline = [];

        for ($i = 0; $i < $months; $i++) {
            $monthStart = $start->copy()->addMonths($i);
            $key = $monthStart->format('Y-m');

            $thu = $projectedIncome + ($receivableByMonth[$key] ?? 0) + $extraIncome;
            $chi = ($behaviorExpense + $recurringExpense) * (1 - $expenseReductionPct);
            $traNo = $debtByMonth[$key] ?? 0;
            if ($extraPaymentLoanId && $extraPaymentAmount > 0) {
                $traNo += $extraPaymentAmount;
            }

            $soDuCuoi = $soDuDau + $thu - $chi - $traNo;

            $flag = null;
            if ($soDuCuoi < 0) {
                $flag = self::FLAG_NEGATIVE;
            } elseif ($soDuCuoi < self::THRESHOLD_RISK) {
                $flag = self::FLAG_RISK;
            } elseif ($soDuCuoi >= self::THRESHOLD_SURPLUS) {
                $flag = self::FLAG_SURPLUS;
            }

            $timeline[] = [
                'month' => $key,
                'month_label' => $monthStart->locale('vi')->monthName . ' ' . $monthStart->year,
                'thu' => round($thu),
                'chi' => round($chi),
                'tra_no' => round($traNo),
                'so_du_dau' => round($soDuDau),
                'so_du_cuoi' => round($soDuCuoi),
                'flag' => $flag,
            ];

            $soDuDau = $soDuCuoi;
        }

        $monthlyExpense = $behaviorExpense + $recurringExpense;
        $minBalance = $timeline ? min(array_column($timeline, 'so_du_cuoi')) : 0;
        $runwayMonths = $this->computeRunwayMonths($timeline);

        $operatingIncomeForDeficit = $projectedIncome + array_sum($receivableByMonth) / max(1, $months);
        $avgMonthlyDebtForDeficit = $months > 0 ? array_sum($debtByMonth) / $months : 0;
        $monthlyDeficitAbsolute = max(0, $monthlyExpense + $avgMonthlyDebtForDeficit - ($operatingIncomeForDeficit + $extraIncome));
        $runwayFromLiquidityMonths = $monthlyDeficitAbsolute > 0 && $availableLiquidity > 0
            ? (int) floor($availableLiquidity / $monthlyDeficitAbsolute) : null;
        $monthlyDeficitPctIncome = $operatingIncomeForDeficit > 0 && $monthlyDeficitAbsolute > 0
            ? $monthlyDeficitAbsolute / $operatingIncomeForDeficit : 0.0;
        $materialityBelow = $this->isDeficitBelowMateriality($monthlyDeficitAbsolute, $operatingIncomeForDeficit);
        $deficitMagnitude = $this->deficitMagnitude($monthlyDeficitAbsolute);

        $alert = $this->buildAlert($timeline, [
            'liquid_balance' => $liquidBalance,
            'available_liquidity' => $availableLiquidity,
            'committed_outflows_30d' => $committedOutflows30d,
            'monthly_deficit_absolute' => $monthlyDeficitAbsolute,
            'runway_from_liquidity_months' => $runwayFromLiquidityMonths,
            'materiality_below' => $materialityBelow,
            'deficit_magnitude' => $deficitMagnitude,
            'operating_income' => $operatingIncomeForDeficit,
        ]);
        $debtExposure = (float) ($position['debt_exposure'] ?? 0);
        $monthlyDebt = $months > 0 ? $debtExposure / 12 : 0;
        $totalDebtByMonth = array_sum($debtByMonth);
        $avgMonthlyDebt = $months > 0 ? $totalDebtByMonth / $months : 0;

        $operatingIncome = $projectedIncome + array_sum($receivableByMonth) / max(1, $months);
        $operatingMargin = $operatingIncome > 0 ? ($operatingIncome - $monthlyExpense) / $operatingIncome : 0.0;
        $debtService = $avgMonthlyDebt;
        $dscr = $debtService > 0 ? ($operatingIncome - $monthlyExpense) / $debtService : null;
        $freeCashflowAfterDebt = $operatingIncome - $monthlyExpense - $debtService;

        $expenseByMonth = $this->expensePurity->getOperationalExpenseMonthlySeries($userId, $linkedAccountNumbers);
        if ($expenseByMonth === []) {
            $expenseByMonth = $this->rawExpenseByMonth($userId, $linkedAccountNumbers);
        }
        ksort($expenseByMonth);
        $expenseChronological = array_values($expenseByMonth);
        $driftExpenseSlopePct = $this->driftSlopePct($expenseChronological);

        $stabilityScore = (float) ($incomeResult['income_stability_score'] ?? 1.0);
        $projectionMode = (string) ($incomeResult['projection_mode'] ?? 'deterministic');
        $incomeP25 = $incomeResult['income_scenario_p25'] ?? null;
        $incomeP50 = $incomeResult['income_scenario_p50'] ?? null;
        $incomeP75 = $incomeResult['income_scenario_p75'] ?? null;
        $requiredRunwayMonths = $stabilityScore < 0.4 ? 6 : 3;

        $linkedAccountCount = (int) ($runContext['linked_account_count'] ?? 0);
        $liquidityStatus = $liquidBalance > 0
            ? 'positive'
            : ($linkedAccountCount > 0 ? 'verified_zero' : 'unknown');

        $monthsWithData = (int) ($incomeResult['sources']['months_with_data'] ?? $incomeResult['window_months'] ?? 0);

        $canonicalRaw = [
            'effective_income' => $operatingIncome,
            'monthly_expense' => $monthlyExpense,
            'monthly_debt' => $avgMonthlyDebt,
            'net_leverage' => (float) ($position['net_leverage'] ?? 0),
            'debt_exposure' => $debtExposure,
            'min_balance' => $minBalance,
            'runway_months' => $runwayMonths,
            'operating_income' => $operatingIncome,
            'operating_expense' => $monthlyExpense,
            'operating_margin' => round($operatingMargin, 4),
            'debt_service' => $debtService,
            'dscr' => $dscr !== null ? round($dscr, 2) : null,
            'free_cashflow_after_debt' => round($freeCashflowAfterDebt),
            'income_stability_score' => $stabilityScore,
            'liquid_balance' => round($liquidBalance),
            'committed_outflows_30d' => round($committedOutflows30d),
            'available_liquidity' => round($availableLiquidity),
            'effective_liquidity' => round($effectiveLiquidity),
            'locked_liquidity' => round($lockedLiquidity),
            'monthly_deficit_absolute' => round($monthlyDeficitAbsolute),
            'monthly_deficit_pct_income' => round($monthlyDeficitPctIncome, 4),
            'materiality_below' => $materialityBelow,
            'deficit_magnitude' => $deficitMagnitude,
            'runway_from_liquidity_months' => $runwayFromLiquidityMonths,
            'projection_mode' => $projectionMode,
            'income_scenario_p25' => $incomeP25,
            'income_scenario_p50' => $incomeP50,
            'income_scenario_p75' => $incomeP75,
            'required_runway_months' => $requiredRunwayMonths,
            'volatility_ratio' => (float) ($incomeResult['volatility_ratio'] ?? 0),
            'liquidity_status' => $liquidityStatus,
            'months_with_data' => $monthsWithData,
        ];
        $economicContext = $runContext['economic_context'] ?? null;
        $forecastErrorHigh = (bool) ($runContext['forecast_error_high'] ?? false);
        $contextAware = app(ContextAwareBufferService::class)->recommend($canonicalRaw, $position, $economicContext, $forecastErrorHigh);
        $requiredRunwayMonths = $contextAware['recommended_runway_months'];
        $canonical = array_merge($canonicalRaw, [
            'required_runway_months' => $requiredRunwayMonths,
            'context_aware_buffer_components' => $contextAware['components'],
        ]);

        $riskResult = $this->riskScoring->score($canonical, $timeline);
        $pillars = $riskResult['pillars'] ?? [];
        $riskResult = $this->consistencyValidator->applyRiskCap($riskResult, $minBalance, $runwayMonths, [
            'liquid_balance' => $liquidBalance,
            'available_liquidity' => $availableLiquidity,
            'materiality_below' => $materialityBelow,
            'runway_from_liquidity_months' => $runwayFromLiquidityMonths,
        ]);

        $trajectoryAnalyzer = app(TrajectoryAnalyzerService::class);
        $surplusSeries = $trajectoryAnalyzer->surplusSeriesFromTimeline($timeline);
        $trajectory = $trajectoryAnalyzer->analyze($surplusSeries);
        $canonicalForCap = array_merge($canonical, [
            'recurring_income' => (int) ($incomeResult['sources']['recurring_income'] ?? $projectedIncome),
            'volatility_ratio' => (float) ($incomeResult['volatility_ratio'] ?? 0),
        ]);
        $capitalStability = app(CapitalStabilityService::class)->score($canonicalForCap, $surplusSeries);
        $maturityStage = app(FinancialMaturityStageService::class)->stage($capitalStability['pillars'], $trajectory);

        $sources = [
            'recurring_income' => (int) ($incomeResult['sources']['recurring_income'] ?? 0),
            'projected_income' => round($projectedIncome),
            'momentum_income' => (int) ($incomeResult['sources']['momentum_income'] ?? 0),
            'recurring_expense' => round($recurringExpense),
            'behavior_expense' => round($behaviorExpense),
            'loan_schedule' => $totalDebtByMonth,
            'shock_mode' => $incomeResult['shock_mode'] ?? false,
            'volatility_ratio' => (float) ($incomeResult['volatility_ratio'] ?? 0),
            'reliability_weight' => (float) ($incomeResult['reliability_weight'] ?? 0),
            'income_stability_index' => (float) ($incomeResult['income_stability_index'] ?? 1),
            'income_stability_score' => (float) ($incomeResult['income_stability_score'] ?? 1),
            'confidence_range_low' => (float) ($incomeResult['confidence_range_low'] ?? $projectedIncome),
            'confidence_range_high' => (float) ($incomeResult['confidence_range_high'] ?? $projectedIncome),
            'confidence_pct' => (float) ($incomeResult['confidence_pct'] ?? 100),
            'income_drift_slope_pct' => $incomeResult['income_drift_slope_pct'] ?? null,
            'drift_expense_slope_pct' => $driftExpenseSlopePct,
            'spike_multiplier_used' => (float) ($incomeResult['spike_multiplier_used'] ?? 2.5),
            'income_coverage_p25' => $incomeResult['income_coverage_p25'],
            'income_coverage_p50' => $incomeResult['income_coverage_p50'],
            'projection_mode' => $projectionMode,
            'income_scenario_p25' => $incomeP25,
            'income_scenario_p50' => $incomeP50,
            'income_scenario_p75' => $incomeP75,
            'required_runway_months' => $requiredRunwayMonths,
            'runway_months' => $runwayMonths,
            'canonical' => $canonical,
            'risk_pillars' => $pillars,
            'months_with_data' => (int) ($incomeResult['sources']['months_with_data'] ?? $incomeResult['window_months'] ?? 0),
            'capital_stability' => $capitalStability,
            'maturity_stage' => $maturityStage,
            'trajectory' => $trajectory,
        ];

        return [
            'timeline' => $timeline,
            'alert' => $alert,
            'risk_score' => $riskResult['score'],
            'risk_label' => $riskResult['label'],
            'risk_color' => $riskResult['color'],
            'sources' => $sources,
        ];
    }

    /**
     * Runway: số tháng có thể sống với dòng tiền hiện tại (đến tháng đầu tiên âm). Không âm = runway = số tháng projection.
     */
    private function computeRunwayMonths(array $timeline): int
    {
        foreach ($timeline as $i => $row) {
            if (($row['so_du_cuoi'] ?? 0) < 0) {
                return $i;
            }
        }

        return count($timeline);
    }

    private function recurringExpensePerMonth(int $userId): float
    {
        return (float) UserRecurringPattern::where('user_id', $userId)
            ->where('status', UserRecurringPattern::STATUS_ACTIVE)
            ->where('direction', 'OUT')
            ->get()
            ->sum(fn ($p) => $this->patternMonthlyAmount($p));
    }

    private function patternMonthlyAmount(UserRecurringPattern $p): float
    {
        $avg = (float) $p->avg_amount;
        $interval = (float) $p->avg_interval_days ?: 30;
        return $avg * (30 / $interval);
    }

    /**
     * Chi tiêu theo hành vi: chỉ OPERATING_EXPENSE (ExpensePurity), EWMA + dynamic window + volatility, spike cap.
     *
     * @param  array<string>  $linkedAccountNumbers
     */
    private function behaviorExpensePerMonth(int $userId, array $linkedAccountNumbers = []): float
    {
        $byMonth = $this->expensePurity->getOperationalExpenseMonthlySeries($userId, $linkedAccountNumbers);
        if ($byMonth === []) {
            $byMonth = $this->rawExpenseByMonth($userId, $linkedAccountNumbers);
        }
        ksort($byMonth);
        $sorted = array_values($byMonth);
        $n = count($sorted);
        if ($n === 0) {
            return 0.0;
        }
        if ($n === 1) {
            return (float) $sorted[0];
        }
        $medianExpense = $this->expenseMedian($sorted);
        $volRatio = $this->expenseVolatilityRatio($sorted);
        $spikeMult = $this->adaptiveSpikeMultiplier($volRatio);
        $sorted = $this->capSpikeMonths($sorted, $medianExpense, $spikeMult);
        $avg = array_sum($sorted) / $n;
        $volatility = $this->expenseStdDev($sorted);
        $volatilityRatio = $avg > 0 ? min(1.0, $volatility / $avg) : 0.0;
        $cfg = config('adaptive_income.window', []);
        $base = (int) ($cfg['base_months'] ?? 12);
        $min = (int) ($cfg['min_months'] ?? 3);
        $max = (int) ($cfg['max_months'] ?? 24);
        $windowMonths = max($min, min($max, $base - (int) round($volatilityRatio * 6)));
        $windowed = array_slice($sorted, -min($windowMonths, $n));
        $alpha = $this->expenseAlphaFromVolatility($volatilityRatio);

        return $this->expenseEwma($windowed, $alpha);
    }

    /**
     * @param  array<string>  $linkedAccountNumbers
     */
    private function rawExpenseByMonth(int $userId, array $linkedAccountNumbers = []): array
    {
        $since = Carbon::now()->subMonths(24)->startOfMonth();
        $query = TransactionHistory::where('user_id', $userId)
            ->where('type', 'OUT')
            ->where('transaction_date', '>=', $since);
        if (! empty($linkedAccountNumbers)) {
            $query->whereIn('account_number', $linkedAccountNumbers);
        }
        $txs = $query->select('amount', 'transaction_date')
            ->get();
        $byMonth = [];
        foreach ($txs as $t) {
            $key = Carbon::parse($t->transaction_date)->format('Y-m');
            if (! isset($byMonth[$key])) {
                $byMonth[$key] = 0;
            }
            $byMonth[$key] += abs((float) $t->amount);
        }
        return $byMonth;
    }

    private function expenseMedian(array $values): float
    {
        if (empty($values)) {
            return 0.0;
        }
        $copy = $values;
        sort($copy);
        $n = count($copy);
        $mid = (int) floor($n / 2);
        return $n % 2 === 0 ? ($copy[$mid - 1] + $copy[$mid]) / 2 : (float) $copy[$mid];
    }

    /** Spike detection: tháng > multiplier * median → cap để baseline không bị thổi phồng. */
    private function capSpikeMonths(array $sorted, float $median, float $multiplier = 2.5): array
    {
        if ($median <= 0) {
            return $sorted;
        }
        $cap = $multiplier * $median;
        return array_map(fn ($v) => $v > $cap ? $cap : $v, $sorted);
    }

    private function expenseVolatilityRatio(array $sorted): float
    {
        $n = count($sorted);
        if ($n < 2) {
            return 0.0;
        }
        $avg = array_sum($sorted) / $n;

        return $avg > 0 ? min(1.0, $this->expenseStdDev($sorted) / $avg) : 0.0;
    }

    private function adaptiveSpikeMultiplier(float $volatilityRatio): float
    {
        $cfg = config('financial_structure.adaptive_threshold', []);
        $base = (float) ($cfg['spike_multiplier_base'] ?? 2.5);
        $volFactor = (float) ($cfg['spike_multiplier_volatility_factor'] ?? 0.5);

        return (float) max(1.5, min(5.0, $base * (1 + $volFactor * $volatilityRatio)));
    }

    private function expenseStdDev(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0.0;
        }
        $avg = array_sum($values) / $n;
        $sumSq = 0.0;
        foreach ($values as $v) {
            $sumSq += ($v - $avg) ** 2;
        }

        return (float) sqrt($sumSq / ($n - 1));
    }

    private function expenseAlphaFromVolatility(float $volatilityRatio): float
    {
        $cfg = config('adaptive_income.ewma', []);
        $alphaMin = (float) ($cfg['alpha_min'] ?? 0.15);
        $alphaMax = (float) ($cfg['alpha_max'] ?? 0.6);
        $high = (float) ($cfg['volatility_high_ratio'] ?? 0.35);
        $low = (float) ($cfg['volatility_low_ratio'] ?? 0.08);
        if ($volatilityRatio <= $low) {
            return $alphaMin;
        }
        if ($volatilityRatio >= $high) {
            return $alphaMax;
        }
        $t = ($volatilityRatio - $low) / max(0.001, $high - $low);

        return $alphaMin + $t * ($alphaMax - $alphaMin);
    }

    /** Linear regression slope % (theo tháng): slope / mean * 100. Chuỗi theo thời gian. */
    private function driftSlopePct(array $chronologicalValues): ?float
    {
        $n = count($chronologicalValues);
        if ($n < 3) {
            return null;
        }
        $mean = array_sum($chronologicalValues) / $n;
        if ($mean <= 0) {
            return null;
        }
        $sumX = $sumY = $sumXY = $sumX2 = 0;
        foreach ($chronologicalValues as $i => $y) {
            $y = (float) $y;
            $sumX += $i;
            $sumY += $y;
            $sumXY += $i * $y;
            $sumX2 += $i * $i;
        }
        $denom = $n * $sumX2 - $sumX * $sumX;
        if (abs($denom) < 1e-10) {
            return null;
        }
        $slope = ($n * $sumXY - $sumX * $sumY) / $denom;

        return (float) ($slope / $mean * 100);
    }

    private function expenseEwma(array $sortedValues, float $alpha): float
    {
        if (empty($sortedValues)) {
            return 0.0;
        }
        $prev = (float) $sortedValues[0];
        for ($i = 1; $i < count($sortedValues); $i++) {
            $prev = $alpha * (float) $sortedValues[$i] + (1 - $alpha) * $prev;
        }

        return $prev;
    }

    /** Trả nợ theo tháng: từ LoanPendingPayment (contract) + ước tính liability. */
    private function loanPaymentByMonth(int $userId, Collection $oweItems, int $months): array
    {
        $start = Carbon::now()->startOfMonth();
        $byMonth = [];
        for ($i = 0; $i < $months; $i++) {
            $byMonth[$start->copy()->addMonths($i)->format('Y-m')] = 0;
        }

        $contractIds = $oweItems->where('source', 'linked')->pluck('entity.id')->filter()->all();
        if (! empty($contractIds)) {
            $pending = LoanPendingPayment::whereIn('loan_contract_id', $contractIds)
                ->whereIn('status', [LoanPendingPayment::STATUS_AWAITING, LoanPendingPayment::STATUS_MATCHED_BANK, LoanPendingPayment::STATUS_PENDING_CONFIRM])
                ->get();
            foreach ($pending as $p) {
                $key = Carbon::parse($p->due_date)->format('Y-m');
                if (isset($byMonth[$key])) {
                    $byMonth[$key] += (float) $p->expected_principal + (float) $p->expected_interest;
                }
            }
        }

        $estimatedPerMonth = 0;
        foreach ($oweItems as $item) {
            if ($item->source === 'personal') {
                $total = (float) $item->outstanding + (float) ($item->unpaid_interest ?? 0);
                $estimatedPerMonth += $total / max(1, $months);
            } elseif ($item->source === 'linked') {
                $contract = $item->entity;
                if ($contract instanceof LoanContract && ! $contract->payment_schedule_enabled) {
                    $total = (float) $item->outstanding + (float) ($item->unpaid_interest ?? 0);
                    $estimatedPerMonth += $total / max(1, $months);
                }
            }
        }

        if ($estimatedPerMonth > 0) {
            $spread = $estimatedPerMonth / $months;
            foreach (array_keys($byMonth) as $key) {
                $byMonth[$key] += $spread;
            }
        }

        return $byMonth;
    }

    /** Khoản thu (đòi nợ) theo tháng - đơn giản: chia đều receivable theo tháng. */
    private function receivableByMonth(Collection $receiveItems, int $months): array
    {
        $total = $receiveItems->sum('outstanding') + $receiveItems->sum('unpaid_interest');
        if ($total <= 0 || $months <= 0) {
            return [];
        }
        $perMonth = $total / $months;
        $start = Carbon::now()->startOfMonth();
        $out = [];
        for ($i = 0; $i < $months; $i++) {
            $out[$start->copy()->addMonths($i)->format('Y-m')] = $perMonth;
        }
        return $out;
    }

    /**
     * Cảnh báo theo ngữ cảnh: nếu thâm hụt dưới materiality và có thanh khoản → message nhẹ, không "vỡ dòng tiền".
     *
     * @param  array<int, array{so_du_cuoi: float, month_label: string}>  $timeline
     * @param  array{liquid_balance: float, monthly_deficit_absolute: float, runway_from_liquidity_months: int|null, materiality_below: bool, deficit_magnitude: string, operating_income: float}  $context
     */
    private function buildAlert(array $timeline, array $context = []): ?string
    {
        $firstNegative = null;
        foreach ($timeline as $row) {
            if (($row['so_du_cuoi'] ?? 0) < 0) {
                $firstNegative = $row;
                break;
            }
        }
        if ($firstNegative === null) {
            return null;
        }
        $x = (int) abs($firstNegative['so_du_cuoi']);
        $y = $firstNegative['month_label'];
        $liquidBalance = (float) ($context['liquid_balance'] ?? 0);
        $monthlyDeficit = (float) ($context['monthly_deficit_absolute'] ?? 0);
        $runwayFromLiq = $context['runway_from_liquidity_months'] ?? null;
        $materialityBelow = (bool) ($context['materiality_below'] ?? false);
        $deficitMagnitude = (string) ($context['deficit_magnitude'] ?? 'significant');
        $income = (float) ($context['operating_income'] ?? 0);

        $availableLiq = (float) ($context['available_liquidity'] ?? $liquidBalance);
        if ($materialityBelow && ($availableLiq > 0 || $runwayFromLiq !== null)) {
            $twelveMonth = (int) round($monthlyDeficit * 12);
            $msg = "Bạn đang thâm hụt nhẹ (khoảng " . number_format($monthlyDeficit) . " ₫/tháng). ";
            $msg .= "Nếu duy trì, sau 12 tháng âm khoảng " . number_format($twelveMonth) . " ₫. ";
            if ($availableLiq > 0 && $runwayFromLiq !== null && $runwayFromLiq >= 12) {
                $msg .= "Mức này không nghiêm trọng nếu có tiền dự phòng (số dư khả dụng đủ trang trải khoảng " . $runwayFromLiq . " tháng).";
            } else {
                $msg .= "Cân nhắc bổ sung thu hoặc giảm chi để cân bằng.";
            }
            return $msg;
        }

        return "Nếu giữ nguyên mức chi hiện tại, bạn sẽ thiếu khoảng " . number_format($x) . " ₫ vào tháng {$y}.";
    }

    private function isDeficitBelowMateriality(float $monthlyDeficitAbsolute, float $operatingIncome): bool
    {
        if ($monthlyDeficitAbsolute <= 0) {
            return true;
        }
        $cfg = config('financial_brain.materiality', []);
        $maxVnd = (float) ($cfg['deficit_absolute_vnd'] ?? 1_000_000);
        $maxPct = (float) ($cfg['deficit_pct_income'] ?? 0.05);
        $pct = $operatingIncome > 0 ? $monthlyDeficitAbsolute / $operatingIncome : 0;

        return $monthlyDeficitAbsolute < $maxVnd || $pct < $maxPct;
    }

    /** tiny | moderate | significant theo VND. */
    private function deficitMagnitude(float $monthlyDeficitAbsolute): string
    {
        if ($monthlyDeficitAbsolute <= 0) {
            return 'none';
        }
        $cfg = config('financial_brain.scale', []);
        $tiny = (float) ($cfg['deficit_tiny_vnd'] ?? 1_000_000);
        $moderate = (float) ($cfg['deficit_moderate_vnd'] ?? 10_000_000);
        if ($monthlyDeficitAbsolute < $tiny) {
            return 'tiny';
        }
        if ($monthlyDeficitAbsolute < $moderate) {
            return 'moderate';
        }

        return 'significant';
    }

    /**
     * Risk dựa trên: continuous deficit, net leverage, income coverage (percentile user), DTI.
     *
     * @param  array{net_leverage: float, debt_exposure: float, receivable_exposure: float}  $position
     */
    private function netRiskScore(
        array $position,
        array $timeline,
        float $avgMonthlyIncome,
        float $avgMonthlyExpense,
        ?float $incomeCoverageP25 = null,
        ?float $incomeCoverageP50 = null
    ): array {
        $net = (float) ($position['net_leverage'] ?? 0);
        $debt = (float) ($position['debt_exposure'] ?? 0);
        $minBalance = $timeline ? min(array_column($timeline, 'so_du_cuoi')) : 0;
        $outflow = $avgMonthlyExpense + ($debt > 0 ? $debt / 12 : 0);
        $income = $avgMonthlyIncome > 0 ? $avgMonthlyIncome : 1;
        $incomeCoverage = $outflow > 0 ? $income / $outflow : 1;
        $dti = $outflow / $income;

        $cfg = config('adaptive_income.risk_percentile', []);
        $thresholdP25 = $incomeCoverageP25 ?? (float) ($cfg['coverage_p25_fallback'] ?? 0.15);
        $thresholdP50 = $incomeCoverageP50 ?? (float) ($cfg['coverage_p50_fallback'] ?? 0.35);

        $deficitMonths = 0;
        foreach ($timeline as $row) {
            if (($row['so_du_cuoi'] ?? 0) < 0) {
                $deficitMonths++;
            }
        }
        $hasNegative = $deficitMonths > 0;
        $allNegative = $deficitMonths === count($timeline) && count($timeline) > 0;
        $firstNegativeMonth = null;
        foreach ($timeline as $i => $row) {
            if (($row['so_du_cuoi'] ?? 0) < 0) {
                $firstNegativeMonth = $i;
                break;
            }
        }
        $survivalHorizonZero = $firstNegativeMonth === 0;

        // Consistency: nếu dòng tiền dương mạnh (không tháng âm, dư tối thiểu) → không được “Nguy hiểm”/“Cực cao”.
        $allPositive = ! $hasNegative && count($timeline) > 0;
        $strongCashflow = $allPositive && $minBalance >= self::MIN_SURPLUS_CAP_RISK;

        $score = 0;
        if ($net < -500_000_000) {
            $score += $strongCashflow ? 0 : 2;
        } elseif ($net < -100_000_000) {
            $score += $strongCashflow ? 0 : 1;
        } elseif ($net < 0) {
            $score += $strongCashflow ? 0 : 1;
        }
        if ($allNegative) {
            $score += 2;
        } elseif ($hasNegative) {
            $score += $strongCashflow ? 0 : 1;
        }
        if ($survivalHorizonZero && $net < -50_000_000) {
            $score += $strongCashflow ? 0 : 2;
        }
        if ($incomeCoverage < $thresholdP25) {
            $score += $strongCashflow ? 0 : 2;
        } elseif ($incomeCoverage < $thresholdP50) {
            $score += $strongCashflow ? 0 : 1;
        }
        if ($dti > 1) {
            $score += $strongCashflow ? 0 : 1;
        } elseif ($dti > 0.8) {
            $score += $strongCashflow ? 0 : 1;
        }

        if ($strongCashflow) {
            return ['score' => self::RISK_STABLE, 'label' => 'Ổn định', 'color' => 'green'];
        }

        if ($score >= 6 || ($survivalHorizonZero && $net < -100_000_000)) {
            return ['score' => self::RISK_CRITICAL, 'label' => 'Cực cao', 'color' => 'red'];
        }
        if ($score >= 4) {
            return ['score' => self::RISK_DANGER, 'label' => 'Nguy hiểm', 'color' => 'red'];
        }
        if ($score >= 2) {
            return ['score' => self::RISK_WARNING, 'label' => 'Cảnh báo', 'color' => 'yellow'];
        }

        return ['score' => self::RISK_STABLE, 'label' => 'Ổn định', 'color' => 'green'];
    }
}
