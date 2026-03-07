<?php

namespace App\Services\TaiChinh;

use App\Models\User;
use App\Services\AdaptiveThresholdService;
use App\Services\AnalyticsAggregateService;
use App\Services\CashflowOptimizationService;
use App\Services\CashflowProjectionService;
use App\Services\DashboardCardService;
use App\Services\TaiChinh\TaiChinhAnalyticsService;
use App\Services\UserFinancialContextService;
use Illuminate\Http\Request;

/**
 * Build từng block cho lazy dashboard API (cards, analytics, debt, projection).
 * Dùng chung logic với TaiChinhIndexViewDataBuilder.
 */
class DashboardBlockService
{
    public function __construct(
        protected UserFinancialContextService $contextService,
        protected DashboardCardService $dashboardCardService,
        protected AdaptiveThresholdService $adaptiveThresholdService,
        protected UnifiedLoansBuilderService $unifiedLoansBuilder,
        protected LiabilitySummaryService $liabilitySummaryService,
        protected FinancialPositionService $financialPositionService,
        protected LoanColumnStatsService $loanColumnStatsService,
        protected AnalyticsAggregateService $analyticsAggregateService,
        protected TaiChinhAnalyticsService $taiChinhAnalyticsService,
        protected CashflowProjectionService $projectionService,
        protected CashflowOptimizationService $optimizationService,
    ) {}

    /**
     * Block cards: events, balance deltas, today/week summary, sync status, per-account.
     */
    public function buildCards(User $user): array
    {
        $context = $this->contextService->ensureCategoriesAndGetContext($user);
        $userBankAccounts = $context['userBankAccounts'];
        $linkedAccountNumbers = $context['linkedAccountNumbers'];
        $accounts = $context['accounts'];
        $balances = $context['accountBalances'] ?? [];
        if (empty($linkedAccountNumbers)) {
            return [
                'card_events' => [],
                'balance_deltas' => ['total' => ['change' => 0, 'percent' => null]],
                'today_summary' => ['total_in' => 0, 'total_out' => 0, 'count' => 0],
                'week_summary' => ['this_week' => ['in' => 0, 'out' => 0], 'last_week' => ['in' => 0, 'out' => 0], 'pct_in' => null, 'pct_out' => null, 'days_compared' => 0],
                'sync_status' => ['has_error' => false, 'by_account' => []],
                'per_account' => [],
            ];
        }
        $todayBatch = $this->dashboardCardService->getTodaySummaryBatch($user->id, $linkedAccountNumbers);
        $weekBatch = $this->dashboardCardService->getWeekSummaryBatch($user->id, $linkedAccountNumbers);
        $deltaBatch = $this->dashboardCardService->getBalanceDeltasBatch($user->id, $linkedAccountNumbers, $balances);

        $todaySummary = [
            'total_in' => array_sum(array_column($todayBatch, 'total_in')),
            'total_out' => array_sum(array_column($todayBatch, 'total_out')),
            'count' => (int) array_sum(array_column($todayBatch, 'count')),
        ];
        $thisWeekIn = array_sum(array_map(fn ($w) => $w['this_week']['in'], $weekBatch));
        $thisWeekOut = array_sum(array_map(fn ($w) => $w['this_week']['out'], $weekBatch));
        $lastWeekIn = array_sum(array_map(fn ($w) => $w['last_week']['in'], $weekBatch));
        $lastWeekOut = array_sum(array_map(fn ($w) => $w['last_week']['out'], $weekBatch));
        $daysCompared = ! empty($weekBatch) ? reset($weekBatch)['days_compared'] : 0;
        $weekSummary = [
            'this_week' => ['in' => $thisWeekIn, 'out' => $thisWeekOut],
            'last_week' => ['in' => $lastWeekIn, 'out' => $lastWeekOut],
            'pct_in' => $lastWeekIn != 0 ? round((($thisWeekIn - $lastWeekIn) / $lastWeekIn) * 100, 1) : null,
            'pct_out' => $lastWeekOut != 0 ? round((($thisWeekOut - $lastWeekOut) / $lastWeekOut) * 100, 1) : null,
            'days_compared' => $daysCompared,
        ];
        $totalChange = array_sum(array_map(fn ($d) => $d['total']['change'] ?? 0, $deltaBatch));
        $totalBalance = array_sum(array_intersect_key($balances, array_flip($linkedAccountNumbers)));
        $totalYesterday = $totalBalance - $totalChange;
        $balanceDeltas = [
            'total' => [
                'change' => (int) $totalChange,
                'percent' => $totalYesterday != 0 ? round((($totalChange / abs($totalYesterday)) * 100), 1) : null,
            ],
        ];

        $syncStatus = $this->dashboardCardService->getSyncStatus($userBankAccounts, $accounts, $user->id);
        $adaptiveThresholds = $this->adaptiveThresholdService->getAdaptiveThresholds($user, $linkedAccountNumbers, $totalBalance > 0 ? $totalBalance : null);
        $giaoDichUrl = route('tai-chinh', ['tab' => 'giao-dich']);
        $firstStk = $userBankAccounts->isNotEmpty() ? trim((string) ($userBankAccounts->first()->account_number ?? '')) : null;
        $batchResult = $this->dashboardCardService->buildCardEventsBatch(
            $user->id,
            $linkedAccountNumbers,
            $balances,
            $todaySummary,
            $weekSummary,
            $syncStatus,
            $balanceDeltas,
            $todayBatch,
            $weekBatch,
            $deltaBatch,
            $giaoDichUrl,
            $user->low_balance_threshold,
            $user->balance_change_amount_threshold ?? $adaptiveThresholds['balance_change_amount'],
            $user->spend_spike_ratio ?? $adaptiveThresholds['spike_ratio'],
            $user->week_anomaly_pct ?? $adaptiveThresholds['week_anomaly_pct'],
            $firstStk
        );
        $cardEvents = $batchResult['all'];

        $perAccount = [];
        foreach ($userBankAccounts as $acc) {
            $stk = trim((string) ($acc->account_number ?? ''));
            if ($stk === '') {
                continue;
            }
            $perAccToday = $todayBatch[$stk] ?? ['total_in' => 0.0, 'total_out' => 0.0, 'count' => 0];
            $perAccWeek = $weekBatch[$stk] ?? ['this_week' => ['in' => 0, 'out' => 0], 'last_week' => ['in' => 0, 'out' => 0], 'pct_in' => null, 'pct_out' => null, 'days_compared' => 0];
            $perAccDelta = $deltaBatch[$stk] ?? ['total' => ['change' => 0, 'percent' => null]];
            $perAccount[$stk] = [
                'today' => $perAccToday,
                'week' => $perAccWeek,
                'balance_delta' => $perAccDelta,
                'events' => $batchResult['by_account'][$stk] ?? [],
            ];
        }

        return [
            'card_events' => $cardEvents,
            'balance_deltas' => $balanceDeltas,
            'today_summary' => $todaySummary,
            'week_summary' => $weekSummary,
            'sync_status' => $syncStatus,
            'per_account' => $perAccount,
        ];
    }

    /**
     * Block analytics: monthly, daily, hourly, byCategory, strategySummary, health_status.
     */
    public function buildAnalytics(User $user, Request $request): array
    {
        $context = $this->contextService->ensureCategoriesAndGetContext($user);
        $linkedAccountNumbers = $context['linkedAccountNumbers'] ?? [];
        $phanTichMonths = (int) $request->input('phan_tich_months', 12);
        $phanTichStk = $request->input('phan_tich_stk');
        $analyticsAccounts = $phanTichStk ? array_filter([$phanTichStk]) : $linkedAccountNumbers;
        if (empty($analyticsAccounts)) {
            return [
                'analytics_data' => [
                    'has_actual_data' => false,
                    'monthly' => [],
                    'daily' => [],
                    'hourly' => [],
                    'byCategory' => ['items' => [], 'concentration' => []],
                    'strategySummary' => [],
                    'health_status' => [],
                ],
            ];
        }
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
        return [
            'analytics_data' => [
                'has_actual_data' => $monthlyResult['has_actual_data'] ?? (($monthlyResult['summary']['total_thu'] ?? 0) + ($monthlyResult['summary']['total_chi'] ?? 0) >= 1),
                'monthly' => $monthlyResult,
                'daily' => $dailyResult,
                'hourly' => $hourlyResult,
                'byCategory' => $byCategory,
                'strategySummary' => $strategySummary,
                'health_status' => $healthStatus,
            ],
        ];
    }

    /**
     * Block debt: position, owe_items, receive_items, liability_summary, owe_stats, receive_stats.
     * Dùng cache financial_context (TTL 5m) để reuse cho projection, analytics, insight.
     */
    public function buildDebt(User $user): array
    {
        $cacheKey = TaiChinhViewCache::financialContextKey($user->id);
        $cached = TaiChinhViewCache::getSafe($cacheKey);
        if ($cached !== null && is_array($cached)) {
            return $cached;
        }
        $context = $this->contextService->ensureCategoriesAndGetContext($user);
        $accountBalances = $context['accountBalances'] ?? [];
        $userLiabilities = $user->userLiabilities()->with(['accruals', 'payments'])->orderBy('direction')->orderBy('status')->orderBy('created_at', 'desc')->get();
        $loanContracts = $this->unifiedLoansBuilder->getLoanContractsForUser($user);
        $unifiedLoans = $this->unifiedLoansBuilder->build($userLiabilities, $loanContracts, $user->id);
        $liabilitySummary = $this->liabilitySummaryService->build($userLiabilities, $loanContracts, $user->id);
        $liquidBalance = array_sum($accountBalances);
        $position = $this->financialPositionService->build($liabilitySummary, $unifiedLoans, $liquidBalance);
        $oweItems = $unifiedLoans->where('is_receivable', false)->values();
        $receiveItems = $unifiedLoans->where('is_receivable', true)->values();
        $oweStats = $this->loanColumnStatsService->build($oweItems->where('is_active', true)->values());
        $receiveStats = $this->loanColumnStatsService->build($receiveItems->where('is_active', true)->values());
        $data = [
            'position' => $position,
            'owe_items' => $oweItems->all(),
            'receive_items' => $receiveItems->all(),
            'liability_summary' => $liabilitySummary,
            'owe_stats' => $oweStats,
            'receive_stats' => $receiveStats,
        ];
        TaiChinhViewCache::putSafe($cacheKey, $data, TaiChinhViewCache::ttlWithJitter(TaiChinhViewCache::TTL_FINANCIAL_CONTEXT_SECONDS));
        return $data;
    }

    /**
     * Block projection: projection + optimization (cho lazy dashboard).
     */
    public function buildProjection(User $user, Request $request): array
    {
        $debt = $this->buildDebt($user);
        $position = $debt['position'];
        $oweItems = collect($debt['owe_items']);
        $receiveItems = collect($debt['receive_items']);
        $activeOwe = $oweItems->where('is_active', true)->values();
        $activeReceive = $receiveItems->where('is_active', true)->values();
        $months = (int) $request->input('months', 12);
        $scenario = array_filter([
            'extra_income_per_month' => $request->input('extra_income_per_month'),
            'expense_reduction_pct' => $request->input('expense_reduction_pct'),
            'extra_payment_loan_id' => $request->input('extra_payment_loan_id'),
            'extra_payment_amount' => $request->input('extra_payment_amount'),
        ], fn ($v) => $v !== null && $v !== '');
        $context = $this->contextService->ensureCategoriesAndGetContext($user);
        $linkedAccountNumbers = $context['linkedAccountNumbers'] ?? [];
        $projection = $this->projectionService->run($user->id, $activeOwe, $activeReceive, $position, $months, $scenario, [
            'linked_account_numbers' => $linkedAccountNumbers,
        ]);
        $optimization = $this->optimizationService->compute($user->id, $activeOwe, $activeReceive, $position, $months);
        return [
            'projection' => $projection,
            'optimization' => $optimization,
        ];
    }
}
