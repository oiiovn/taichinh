<?php

namespace App\Services\TaiChinh;

use App\Models\BudgetThreshold;
use App\Models\IncomeGoal;
use App\Models\PaymentSchedule;
use App\Services\PaymentScheduleObligationService;
use App\Models\SystemCategory;
use App\Models\TransactionHistory;
use App\Services\AdaptiveThresholdService;
use App\Services\AnalyticsAggregateService;
use App\Services\BudgetThresholdService;
use App\Services\DashboardCardService;
use App\Services\DriftAnalyzerService;
use App\Services\DualAxisAwarenessService;
use App\Services\FinancialInsightPipeline;
use App\Services\IncomeGoalService;
use App\Services\InsightPayloadService;
use App\Services\UserFinancialContextService;
use App\Http\Controllers\GoiHienTaiController;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TaiChinhIndexViewDataBuilder
{
    /** Tab chỉ cần context + budget/schedule/loans, bỏ qua insight + analytics + dashboard để load ~5s sau khi ghi. */
    private const LIGHT_TABS = ['lich-thanh-toan', 'tai-khoan', 'nguong-ngan-sach', 'giao-dich', 'no-khoan-vay'];

    public function __construct(
        protected UserFinancialContextService $contextService,
        protected UnifiedLoansBuilderService $unifiedLoansBuilder,
        protected LiabilitySummaryService $liabilitySummaryService,
        protected FinancialPositionService $financialPositionService,
        protected LoanColumnStatsService $loanColumnStatsService,
        protected FinancialInsightPipeline $insightPipeline,
        protected DualAxisAwarenessService $dualAxisService,
        protected AnalyticsAggregateService $analyticsAggregateService,
        protected TaiChinhAnalyticsService $taiChinhAnalyticsService,
        protected DashboardCardService $dashboardCardService,
        protected AdaptiveThresholdService $adaptiveThresholdService,
        protected BudgetThresholdService $budgetThresholdService,
        protected IncomeGoalService $incomeGoalService,
    ) {}

    public function buildForAjaxTable(Request $request): array
    {
        $user = $request->user();
        $context = $user ? $this->contextService->ensureCategoriesAndGetContext($user) : $this->emptyContext();
        $linkedAccountNumbers = $context['linkedAccountNumbers'];
        $transactions = $user && ! empty($linkedAccountNumbers)
            ? $this->contextService->getPaginatedTransactions($user, $linkedAccountNumbers, $request, 50)
            : TransactionHistory::whereRaw('1 = 0')->paginate(50)->withQueryString();
        return ['transactionHistory' => $transactions];
    }

    public function build(Request $request): array
    {
        $user = $request->user();
        $context = $user ? $this->contextService->ensureCategoriesAndGetContext($user) : $this->emptyContext();
        $userBankAccounts = $context['userBankAccounts'];
        $linkedAccountNumbers = $context['linkedAccountNumbers'];
        $accounts = $context['accounts'];
        $accountBalances = $context['accountBalances'];

        $transactions = $user && ! empty($linkedAccountNumbers)
            ? $this->contextService->getPaginatedTransactions($user, $linkedAccountNumbers, $request, 50)
            : TransactionHistory::whereRaw('1 = 0')->paginate(50)->withQueryString();

        $userCategories = $user ? $user->userCategories()->withCount('transactionHistories')->orderByDesc('transaction_histories_count')->orderBy('type')->orderBy('name')->get() : collect();
        $plans = \App\Models\PlanConfig::getList();
        $currentPlan = $user?->plan;
        $planExpiresAt = $user?->plan_expires_at;
        $planExpiringSoon = $user && $currentPlan && $planExpiresAt && GoiHienTaiController::planExpiresWithinDays($planExpiresAt, 3);
        $maxAccounts = $currentPlan && isset($plans[$currentPlan]) ? (int) $plans[$currentPlan]['max_accounts'] : 0;
        $currentAccountCount = $userBankAccounts->count();
        $canAddAccount = $maxAccounts > 0 && $planExpiresAt && $planExpiresAt->isFuture() && $currentAccountCount < $maxAccounts;

        $userLiabilities = $user ? $user->userLiabilities()->with(['accruals', 'payments'])->orderBy('direction')->orderBy('status')->orderBy('created_at', 'desc')->get() : collect();
        $loanContracts = $user ? $this->unifiedLoansBuilder->getLoanContractsForUser($user) : collect();
        $unifiedLoans = $this->unifiedLoansBuilder->build($userLiabilities, $loanContracts, $user?->id ?? 0);
        $liabilitySummary = $this->liabilitySummaryService->build($userLiabilities, $loanContracts, $user?->id ?? 0);
        $liquidBalance = array_sum($accountBalances ?? []);
        $position = $this->financialPositionService->build($liabilitySummary, $unifiedLoans, $liquidBalance);
        $oweItems = $unifiedLoans->where('is_receivable', false)->values();
        $receiveItems = $unifiedLoans->where('is_receivable', true)->values();
        $oweStats = $this->loanColumnStatsService->build($oweItems->where('is_active', true)->values());
        $receiveStats = $this->loanColumnStatsService->build($receiveItems->where('is_active', true)->values());

        $contextPayload = [
            'position' => $position,
            'oweItems' => $oweItems,
            'receiveItems' => $receiveItems,
            'currentAccountCount' => $currentAccountCount,
            'projection_months' => $request->input('projection_months', 12),
            'refresh_insight' => $request->has('refresh_insight'),
            'extra_income_per_month' => $request->input('extra_income_per_month'),
            'expense_reduction_pct' => $request->input('expense_reduction_pct'),
            'extra_payment_loan_id' => $request->input('extra_payment_loan_id'),
            'extra_payment_amount' => $request->input('extra_payment_amount'),
            'pay2sAccounts' => $accounts,
            'accountBalances' => $accountBalances ?? [],
            'transactionHistory' => $transactions,
            'userBankAccounts' => $userBankAccounts,
            'userCategories' => $userCategories ?? collect(),
            'linkedAccountNumbers' => $linkedAccountNumbers ?? [],
            'maxAccounts' => $maxAccounts,
            'canAddAccount' => $canAddAccount,
            'currentPlan' => $currentPlan,
            'planExpiresAt' => $planExpiresAt,
            'planExpiringSoon' => $planExpiringSoon ?? false,
            'userLiabilities' => $userLiabilities,
            'liabilitySummary' => $liabilitySummary,
            'unifiedLoans' => $unifiedLoans,
            'oweStats' => $oweStats,
            'receiveStats' => $receiveStats,
        ];

        $tab = $request->get('tab', 'dashboard');
        $isLightTab = in_array($tab, self::LIGHT_TABS, true);
        if ($user && $request->boolean('refresh_insight')) {
            TaiChinhViewCache::forgetHeavy($user->id);
        }

        if ($isLightTab && $user) {
            $viewData = array_merge($contextPayload, [
                'insight_from_cache' => null,
                'projection' => null,
                'projectionOptimization' => null,
                'oweItemsForProjection' => $oweItems,
                'financialState' => null,
                'priorityMode' => null,
                'contextualFrame' => null,
                'objective' => null,
                'narrativeResult' => null,
                'insightPayload' => null,
                'insightHash' => null,
                'dataSufficiency' => null,
                'insufficientData' => true,
                'onboardingNarrative' => null,
                'dualAxis' => $this->dualAxisService->compute(null, true, null, null),
                'timelineSnapshots' => [],
                'timelineMaturity' => 'new',
                'analyticsData' => null,
                'dashboardCardEvents' => [],
                'dashboardBalanceDeltas' => [],
                'dashboardTodaySummary' => [],
                'dashboardWeekSummary' => [],
                'dashboardSyncStatus' => ['has_error' => false, 'by_account' => []],
                'dashboardPerAccount' => [],
            ]);
        } elseif ($user) {
            $viewData = $this->insightPipeline->run($user, $contextPayload)->toArray();
            $viewData['timelineSnapshots'] = $this->loadTimelineSnapshotsForStrategy($user->id);
            $viewData['timelineMaturity'] = $this->computeTimelineMaturity(
                $viewData['dataSufficiency'] ?? [],
                isset($viewData['insightPayload']['cognitive_input']['liquidity_context']['liquidity_status'])
                    ? $viewData['insightPayload']['cognitive_input']['liquidity_context']['liquidity_status'] : null
            );
            $viewData['insight_from_cache'] = false;
        } else {
            $viewData = array_merge($contextPayload, [
                'insight_from_cache' => null,
                'projection' => null,
                'projectionOptimization' => null,
                'oweItemsForProjection' => $oweItems,
                'financialState' => null,
                'priorityMode' => null,
                'contextualFrame' => null,
                'objective' => null,
                'narrativeResult' => null,
                'insightPayload' => null,
                'insightHash' => null,
                'dataSufficiency' => null,
                'insufficientData' => true,
                'onboardingNarrative' => null,
                'dualAxis' => $this->dualAxisService->compute(null, true, null, null),
            ]);
            $viewData['timelineSnapshots'] = [];
            $viewData['timelineMaturity'] = 'new';
        }

        if (! $isLightTab) {
            $this->attachAnalyticsData($user, $linkedAccountNumbers, $request, $viewData);
            $this->attachDashboardData($user, $userBankAccounts, $linkedAccountNumbers, $accounts, $accountBalances, $viewData);
        }
        $viewData['title'] = 'Tài chính';
        $viewData['insightGptPrompt'] = InsightPayloadService::GPT_SYSTEM_PROMPT;
        $this->attachBudgetAndIncomeGoalData($user, $linkedAccountNumbers, $request, $viewData);
        $viewData['paymentSchedules'] = $user ? PaymentSchedule::where('user_id', $user->id)->orderBy('next_due_date')->get() : collect();
        if ($user) {
            $obligationService = app(PaymentScheduleObligationService::class);
            $viewData['paymentScheduleObligation30'] = $obligationService->obligationsNext30Days($user->id);
            $viewData['paymentScheduleObligation90'] = $obligationService->obligationsNext90Days($user->id);
            $viewData['paymentScheduleExecutionStatus'] = [];
            foreach ($viewData['paymentSchedules'] as $s) {
                $viewData['paymentScheduleExecutionStatus'][$s->id] = $obligationService->getExecutionStatus($s);
            }
            $bySchedule30 = [];
            foreach ($viewData['paymentScheduleObligation30']['items'] ?? [] as $item) {
                $sid = (int) ($item['schedule_id'] ?? 0);
                if ($sid) {
                    $bySchedule30[$sid] = ($bySchedule30[$sid] ?? 0) + (float) ($item['amount'] ?? 0);
                }
            }
            $viewData['scheduleObligation30dAmount'] = $bySchedule30;
        } else {
            $viewData['paymentScheduleObligation30'] = ['total' => 0, 'items' => []];
            $viewData['paymentScheduleObligation90'] = ['total' => 0, 'items' => []];
            $viewData['paymentScheduleExecutionStatus'] = [];
            $viewData['scheduleObligation30dAmount'] = [];
        }

        return $viewData;
    }

    private function emptyContext(): array
    {
        return [
            'userBankAccounts' => collect(),
            'linkedAccountNumbers' => [],
            'accounts' => collect(),
            'accountBalances' => [],
        ];
    }

    private function attachAnalyticsData(?object $user, array $linkedAccountNumbers, Request $request, array &$viewData): void
    {
        if (! $user) {
            $viewData['analyticsData'] = null;
            return;
        }
        $phanTichMonths = (int) $request->input('phan_tich_months', 12);
        $phanTichStk = $request->input('phan_tich_stk');
        $analyticsAccounts = $phanTichStk ? array_filter([$phanTichStk]) : $linkedAccountNumbers;
        $monthlyResult = $this->analyticsAggregateService->monthlyInOut($user->id, $analyticsAccounts, $phanTichMonths);
        $byCategory = $this->analyticsAggregateService->expenseByCategory($user->id, $analyticsAccounts, $phanTichMonths);
        $categoryItems = $byCategory['items'] ?? [];
        $concentration = $byCategory['concentration'] ?? [];
        if (! empty($concentration) && ($concentration['top1_pct'] ?? 0) >= 75 && ! empty($categoryItems)) {
            $topName = $categoryItems[0]['name'] ?? 'Một danh mục';
            $monthlyResult['anomaly_alerts'][] = [
                'type' => 'category_concentration',
                'message' => sprintf('%s chiếm %s%% tổng chi — rủi ro tập trung cao.', $topName, number_format($concentration['top1_pct'], 0)),
            ];
        }
        $strategySummary = $this->taiChinhAnalyticsService->strategySummary($monthlyResult, $phanTichMonths);
        $healthStatus = $this->taiChinhAnalyticsService->healthStatus($monthlyResult['summary'] ?? [], $strategySummary);
        $dailyResult = $this->analyticsAggregateService->dailyInOut($user->id, $analyticsAccounts, 60);
        $hourlyResult = $this->analyticsAggregateService->hourlyInOut($user->id, $analyticsAccounts);
        $viewData['analyticsData'] = [
            'has_actual_data' => $monthlyResult['has_actual_data'] ?? (($monthlyResult['summary']['total_thu'] ?? 0) + ($monthlyResult['summary']['total_chi'] ?? 0) >= 1),
            'monthly' => $monthlyResult,
            'daily' => $dailyResult,
            'hourly' => $hourlyResult,
            'byCategory' => $byCategory,
            'strategySummary' => $strategySummary,
            'health_status' => $healthStatus,
        ];
    }

    private function attachDashboardData(?object $user, Collection $userBankAccounts, array $linkedAccountNumbers, Collection $accounts, array $accountBalances, array &$viewData): void
    {
        $viewData['dashboardCardEvents'] = [];
        $viewData['dashboardBalanceDeltas'] = [];
        $viewData['dashboardTodaySummary'] = [];
        $viewData['dashboardWeekSummary'] = [];
        $viewData['dashboardSyncStatus'] = ['has_error' => false, 'by_account' => []];
        $viewData['dashboardPerAccount'] = [];
        if (! $user || empty($linkedAccountNumbers)) {
            return;
        }
        $viewData['dashboardBalanceDeltas'] = $this->dashboardCardService->getBalanceDeltas($user->id, $linkedAccountNumbers, $viewData['accountBalances'] ?? []);
        $viewData['dashboardTodaySummary'] = $this->dashboardCardService->getTodaySummary($user->id, $linkedAccountNumbers);
        $viewData['dashboardWeekSummary'] = $this->dashboardCardService->getWeekSummary($user->id, $linkedAccountNumbers);
        $viewData['dashboardSyncStatus'] = $this->dashboardCardService->getSyncStatus($userBankAccounts, $accounts, $user->id);
        $totalBalance = array_sum(array_intersect_key($viewData['accountBalances'] ?? [], array_flip($linkedAccountNumbers)));
        $adaptiveThresholds = $this->adaptiveThresholdService->getAdaptiveThresholds($user, $linkedAccountNumbers, $totalBalance > 0 ? $totalBalance : null);
        $viewData['dashboardCardEvents'] = $this->dashboardCardService->buildCardEvents(
            $user->id,
            $linkedAccountNumbers,
            $viewData['accountBalances'] ?? [],
            $viewData['dashboardTodaySummary'],
            $viewData['dashboardWeekSummary'],
            $viewData['dashboardSyncStatus'],
            $viewData['dashboardBalanceDeltas'],
            route('tai-chinh', ['tab' => 'giao-dich']),
            $user->low_balance_threshold,
            $user->balance_change_amount_threshold ?? $adaptiveThresholds['balance_change_amount'],
            $user->spend_spike_ratio ?? $adaptiveThresholds['spike_ratio'],
            $user->week_anomaly_pct ?? $adaptiveThresholds['week_anomaly_pct']
        );
        foreach ($userBankAccounts as $acc) {
            $stk = trim((string) ($acc->account_number ?? ''));
            if ($stk === '') {
                continue;
            }
            $bal = ($viewData['accountBalances'] ?? [])[$stk] ?? 0;
            $perAccToday = $this->dashboardCardService->getTodaySummary($user->id, [$stk]);
            $perAccWeek = $this->dashboardCardService->getWeekSummary($user->id, [$stk]);
            $perAccBalanceDelta = $this->dashboardCardService->getBalanceDeltas($user->id, [$stk], [$stk => $bal]);
            $viewData['dashboardPerAccount'][$stk] = [
                'today' => $perAccToday,
                'week' => $perAccWeek,
                'balance_delta' => $perAccBalanceDelta,
                'events' => $this->dashboardCardService->buildCardEvents(
                    $user->id,
                    [$stk],
                    [$stk => $bal],
                    $perAccToday,
                    $perAccWeek,
                    $viewData['dashboardSyncStatus'],
                    $perAccBalanceDelta,
                    route('tai-chinh', ['tab' => 'giao-dich']),
                    $user->low_balance_threshold,
                    $user->balance_change_amount_threshold ?? $adaptiveThresholds['balance_change_amount'],
                    $user->spend_spike_ratio ?? $adaptiveThresholds['spike_ratio'],
                    $user->week_anomaly_pct ?? $adaptiveThresholds['week_anomaly_pct']
                ),
            ];
        }
    }

    private function attachBudgetAndIncomeGoalData(?object $user, array $linkedAccountNumbers, Request $request, array &$viewData): void
    {
        $viewData['systemCategoriesExpense'] = SystemCategory::whereIn('type', ['expense'])->orderBy('name')->get();
        $viewData['openModalNguong'] = $request->get('tab') === 'nguong-ngan-sach' && $request->boolean('openModal');
        $viewData['openModalMucTieuThu'] = $request->get('tab') === 'nguong-ngan-sach' && $request->boolean('openModalGoal');
        $viewData['budgetThresholds'] = collect();
        $viewData['editThreshold'] = null;
        $viewData['budgetThresholdSummary'] = [];
        $viewData['incomeGoals'] = collect();
        $viewData['editIncomeGoal'] = null;
        $viewData['incomeGoalSummary'] = [];

        try {
            $viewData['budgetThresholds'] = $user ? BudgetThreshold::where('user_id', $user->id)->where('is_active', true)->orderByDesc('created_at')->get() : collect();
            if ($user && $request->integer('edit') > 0 && $request->get('tab') === 'nguong-ngan-sach') {
                $viewData['editThreshold'] = BudgetThreshold::where('user_id', $user->id)->where('id', $request->integer('edit'))->first();
            }
            if ($user && ! empty($linkedAccountNumbers)) {
                $viewData['budgetThresholdSummary'] = $this->budgetThresholdService->getThresholdSummaryForUser($user->id, $linkedAccountNumbers);
            }

            $viewData['incomeGoals'] = $user ? IncomeGoal::where('user_id', $user->id)->where('is_active', true)->orderByDesc('created_at')->get() : collect();
            if ($user && $request->integer('edit_goal') > 0 && $request->get('tab') === 'nguong-ngan-sach') {
                $viewData['editIncomeGoal'] = IncomeGoal::where('user_id', $user->id)->where('id', $request->integer('edit_goal'))->first();
            }
            if ($user && ! empty($linkedAccountNumbers)) {
                $viewData['incomeGoalSummary'] = $this->incomeGoalService->getGoalSummaryForUser($user->id, $linkedAccountNumbers);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('attachBudgetAndIncomeGoalData failed', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Snapshots cho timeline hành trình (cột phải trang Chiến lược). Mới nhất trước (trên xuống = thời gian giảm dần).
     */
    private function loadTimelineSnapshotsForStrategy(int $userId): array
    {
        $drift = app(DriftAnalyzerService::class);
        return $drift->loadLastSnapshots($userId, 10);
    }

    /**
     * Data Maturity cho timeline: new (< 2 tháng) | medium (3–6) | mature (6+).
     * liquidity_status unknown → tối đa medium.
     */
    private function computeTimelineMaturity(array $dataSufficiency, ?string $liquidityStatus): string
    {
        $months = (int) ($dataSufficiency['months_with_data'] ?? 0);
        if (strtolower((string) $liquidityStatus) === 'unknown') {
            $months = min($months, 5);
        }
        if ($months < 2) {
            return 'new';
        }
        if ($months < 6) {
            return 'medium';
        }
        return 'mature';
    }
}
