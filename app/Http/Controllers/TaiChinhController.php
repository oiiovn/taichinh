<?php

namespace App\Http\Controllers;

use App\Jobs\UpdateGlobalMerchantPatternJob;
use App\Models\GlobalMerchantPattern;
use App\Models\FinancialInsightFeedback;
use App\Models\UserBehaviorPattern;
use App\Models\UserMerchantRule;
use App\Services\CashflowOptimizationService;
use App\Services\CashflowProjectionService;
use App\Services\DashboardCardService;
use App\Services\InsightPayloadService;
use App\Services\AdaptiveThresholdService;
use App\Services\UserStrategyProfileService;
use App\Services\MerchantKeyNormalizer;
use App\Services\TransactionClassifier;
use App\Services\TaiChinh\UnifiedLoansBuilderService;
use App\Services\TaiChinh\LiabilitySummaryService;
use App\Services\TaiChinh\FinancialPositionService;
use App\Services\TaiChinh\LoanColumnStatsService;
use App\Services\TaiChinh\LiquidBalanceService;
use App\Services\TaiChinh\TaiChinhAnalyticsService;
use App\Services\TaiChinh\TaiChinhIndexViewDataBuilder;
use App\Services\UserFinancialContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class TaiChinhController extends Controller
{
    public function index(Request $request): View
    {
        try {
            return $this->indexHandle($request);
        } catch (\Throwable $e) {
            Log::error('TaiChinhController@index: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return view('pages.tai-chinh', $this->minimalViewDataForError(
                $request,
                'Không tải được dữ liệu tài chính. Vui lòng thử lại sau.'
            ));
        }
    }

    private function indexHandle(Request $request): View
    {
        $builder = app(TaiChinhIndexViewDataBuilder::class);
        if ($request->ajax() || $request->has('ajax')) {
            return response()->view('pages.tai-chinh.partials.giao-dich-table', $builder->buildForAjaxTable($request));
        }
        return view('pages.tai-chinh', $builder->build($request));
    }

    /**
     * Trang xem chi tiết sự kiện thẻ (một sự kiện theo type/stk hoặc danh sách).
     */
    public function suKien(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        try {
            return $this->suKienHandle($request);
        } catch (\Throwable $e) {
            Log::error('TaiChinhController@suKien: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->route('tai-chinh')->with('error', 'Không tải được trang sự kiện. Vui lòng thử lại sau.');
        }
    }

    private function suKienHandle(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }
        $context = app(UserFinancialContextService::class)->getContext($user);
        $userBankAccounts = $context['userBankAccounts'];
        $linkedAccountNumbers = $context['linkedAccountNumbers'];
        $accounts = $context['accounts'];
        $accountBalances = $context['accountBalances'];
        $dashboardCardEvents = [];
        if (! empty($linkedAccountNumbers)) {
            $dashboardSvc = app(DashboardCardService::class);
            $dashboardBalanceDeltas = $dashboardSvc->getBalanceDeltas($user->id, $linkedAccountNumbers, $accountBalances);
            $dashboardTodaySummary = $dashboardSvc->getTodaySummary($user->id, $linkedAccountNumbers);
            $dashboardWeekSummary = $dashboardSvc->getWeekSummary($user->id, $linkedAccountNumbers);
            $dashboardSyncStatus = $dashboardSvc->getSyncStatus($userBankAccounts, $accounts, $user->id);
            $totalBalance = array_sum(array_intersect_key($accountBalances, array_flip($linkedAccountNumbers)));
            $adaptiveThresholds = app(AdaptiveThresholdService::class)->getAdaptiveThresholds($user, $linkedAccountNumbers, $totalBalance > 0 ? $totalBalance : null);
            $dashboardCardEvents = $dashboardSvc->buildCardEvents(
                $user->id,
                $linkedAccountNumbers,
                $accountBalances,
                $dashboardTodaySummary,
                $dashboardWeekSummary,
                $dashboardSyncStatus,
                $dashboardBalanceDeltas,
                route('tai-chinh', ['tab' => 'giao-dich']),
                $user->low_balance_threshold,
                $user->balance_change_amount_threshold ?? $adaptiveThresholds['balance_change_amount'],
                $user->spend_spike_ratio ?? $adaptiveThresholds['spike_ratio'],
                $user->week_anomaly_pct ?? $adaptiveThresholds['week_anomaly_pct']
            );
        }
        $type = $request->input('type');
        $stk = $request->input('stk');
        $matched = $dashboardCardEvents;
        if ($type !== null && $type !== '') {
            $matched = array_values(array_filter($matched, function ($e) use ($type) {
                return ($e['type'] ?? '') === $type;
            }));
        }
        if ($stk !== null && $stk !== '') {
            $stk = (string) $stk;
            $matched = array_values(array_filter($matched, function ($e) use ($stk) {
                $nums = $e['account_numbers'] ?? null;
                if (is_array($nums)) {
                    foreach ($nums as $acc) {
                        if (substr((string) $acc, -4) === $stk) {
                            return true;
                        }
                    }
                    return false;
                }
                $acc = $e['account_number'] ?? '';
                return $acc !== '' && substr($acc, -4) === $stk;
            }));
        }
        $event = count($matched) === 1 ? $matched[0] : null;

        $eventDetail = $event ? DashboardCardService::getEventExplanationAndAction($event['type'] ?? '') : null;
        $lowBalanceThreshold = $user->low_balance_threshold ?? DashboardCardService::LOW_BALANCE_THRESHOLD_DEFAULT;

        return view('pages.tai-chinh.su-kien', [
            'title' => 'Chi tiết sự kiện',
            'event' => $event,
            'eventDetail' => $eventDetail,
            'events' => $event ? [$event] : $dashboardCardEvents,
            'lowBalanceThreshold' => $lowBalanceThreshold,
        ]);
    }

    /**
     * POST đánh dấu event đã xem (acknowledged) — ẩn trong COOLDOWN_DAYS.
     */
    public function acknowledgeEvent(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('tai-chinh')->with('error', 'Vui lòng đăng nhập.');
        }
        $type = $request->input('type');
        if (empty($type) || ! is_string($type)) {
            return redirect()->back()->with('error', 'Thiếu loại sự kiện.');
        }
        $eventKey = $request->input('stk');
        if ($eventKey !== null) {
            $eventKey = trim((string) $eventKey);
        } else {
            $eventKey = '';
        }
        \App\Models\DashboardEventState::updateOrCreate(
            [
                'user_id' => $user->id,
                'event_type' => $type,
                'event_key' => $eventKey ?: null,
            ],
            [
                'status' => \App\Models\DashboardEventState::STATUS_ACKNOWLEDGED,
                'acknowledged_at' => now(),
            ]
        );
        return redirect()->back()->with('success', 'Đã đánh dấu đã xem. Cảnh báo này sẽ ẩn trong vài ngày.');
    }

    /**
     * POST lưu ngưỡng cảnh báo số dư thấp (VND).
     */
    public function updateLowBalanceThreshold(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }
        $v = Validator::make($request->all(), [
            'threshold' => 'required|numeric|min:0',
        ], [
            'threshold.required' => 'Vui lòng nhập số tiền ngưỡng.',
            'threshold.numeric' => 'Ngưỡng phải là số.',
            'threshold.min' => 'Ngưỡng không được âm.',
        ]);
        if ($v->fails()) {
            $back = $request->input('redirect_url', route('tai-chinh.su-kien', ['type' => 'low_balance']));
            return redirect()->to($back)->withErrors($v)->withInput();
        }
        $value = (int) round((float) $request->input('threshold'));
        $user->update(['low_balance_threshold' => $value > 0 ? $value : null]);
        $back = $request->input('redirect_url', route('tai-chinh.su-kien', ['type' => 'low_balance']));
        return redirect()->to($back)->with('success', 'Đã lưu ngưỡng cảnh báo số dư thấp: ' . number_format($value, 0, ',', '.') . ' ₫.');
    }

    /**
     * POST lưu phản hồi nhanh cho Insight (Inline Strategic Feedback).
     */
    public function storeInsightFeedback(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'insight_hash' => 'required|string|size:64',
            'feedback_type' => 'required|in:agree,infeasible,incorrect,alternative',
            'reason_code' => 'nullable|in:cannot_increase_income,cannot_reduce_expense,no_more_borrowing,no_asset_sale',
            'root_cause' => 'nullable|string|max:64',
            'suggested_action_type' => 'nullable|string|max:64',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->input('feedback_type') === FinancialInsightFeedback::TYPE_INFEASIBLE && ! $request->filled('reason_code')) {
            return response()->json(['errors' => ['reason_code' => ['Vui lòng chọn lý do khi đánh dấu Không khả thi.']]], 422);
        }

        try {
            FinancialInsightFeedback::create([
                'user_id' => $user->id,
                'insight_hash' => $request->input('insight_hash'),
                'root_cause' => $request->input('root_cause'),
                'suggested_action_type' => $request->input('suggested_action_type'),
                'feedback_type' => $request->input('feedback_type'),
                'reason_code' => $request->input('reason_code'),
            ]);

            app(\App\Services\InsightFeedbackToBehaviorLogService::class)->logFromFeedback(
                $user,
                $request->input('feedback_type'),
                $request->input('reason_code'),
                $request->input('root_cause')
            );

            app(\App\Services\UserStrategyProfileService::class)->refreshProfileAfterFeedback($user->id);

            return response()->json(['success' => true, 'message' => 'Đã ghi nhận. Hệ thống sẽ điều chỉnh chiến lược.']);
        } catch (\Throwable $e) {
            Log::error('TaiChinhController@storeInsightFeedback: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Đã xảy ra lỗi. Vui lòng thử lại sau.'], 500);
        }
    }

    /**
     * API trả projection theo scenario (JSON) cho scenario simulation.
     */
    public function projection(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $userLiabilities = $user->userLiabilities()->with(['accruals', 'payments'])->orderBy('direction')->orderBy('status')->orderBy('created_at', 'desc')->get();
            $unifiedLoansBuilder = app(UnifiedLoansBuilderService::class);
            $loanContracts = $unifiedLoansBuilder->getLoanContractsForUser($user);
            $unifiedLoans = $unifiedLoansBuilder->build($userLiabilities, $loanContracts, $user->id);
            $liabilitySummary = app(LiabilitySummaryService::class)->build($userLiabilities, $loanContracts, $user->id);
            $liquidBalance = app(LiquidBalanceService::class)->forUser($user);
            $position = app(FinancialPositionService::class)->build($liabilitySummary, $unifiedLoans, $liquidBalance);
            $oweItems = $unifiedLoans->where('is_receivable', false)->values();
            $receiveItems = $unifiedLoans->where('is_receivable', true)->values();
            $activeOwe = $oweItems->where('is_active', true)->values();
            $activeReceive = $receiveItems->where('is_active', true)->values();

            $months = (int) $request->input('months', 12);
            $scenario = array_filter([
                'extra_income_per_month' => $request->input('extra_income_per_month'),
                'expense_reduction_pct' => $request->input('expense_reduction_pct'),
                'extra_payment_loan_id' => $request->input('extra_payment_loan_id'),
                'extra_payment_amount' => $request->input('extra_payment_amount'),
            ], fn ($v) => $v !== null && $v !== '');

            $linkedAccountNumbers = app(UserFinancialContextService::class)->getLinkedAccountNumbers($user);
            $projection = app(CashflowProjectionService::class)->run($user->id, $activeOwe, $activeReceive, $position, $months, $scenario, [
                'linked_account_numbers' => $linkedAccountNumbers,
            ]);

            return response()->json($projection);
        } catch (\Throwable $e) {
            Log::error('TaiChinhController@projection: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Không tính được dự báo. Vui lòng thử lại sau.'], 500);
        }
    }

    /**
     * API trả payload 5 tầng + prompt cho GPT (Strategic Engine).
     * GET ?scenarios=1 để thêm 3 kịch bản baseline / tăng thu / giảm chi.
     */
    public function insightPayload(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $userLiabilities = $user->userLiabilities()->with(['accruals', 'payments'])->orderBy('direction')->orderBy('status')->orderBy('created_at', 'desc')->get();
            $unifiedLoansBuilder = app(UnifiedLoansBuilderService::class);
            $loanContracts = $unifiedLoansBuilder->getLoanContractsForUser($user);
            $unifiedLoans = $unifiedLoansBuilder->build($userLiabilities, $loanContracts, $user->id);
            $liabilitySummary = app(LiabilitySummaryService::class)->build($userLiabilities, $loanContracts, $user->id);
            $liquidBalance = app(LiquidBalanceService::class)->forUser($user);
            $position = app(FinancialPositionService::class)->build($liabilitySummary, $unifiedLoans, $liquidBalance);
            $oweItems = $unifiedLoans->where('is_receivable', false)->values();
            $receiveItems = $unifiedLoans->where('is_receivable', true)->values();
            $activeOwe = $oweItems->where('is_active', true)->values();
            $activeReceive = $receiveItems->where('is_active', true)->values();
            $months = (int) $request->input('months', 12);

            $linkedAccountNumbers = app(UserFinancialContextService::class)->getLinkedAccountNumbers($user);
            $projection = app(CashflowProjectionService::class)->run($user->id, $activeOwe, $activeReceive, $position, $months, [], [
                'linked_account_numbers' => $linkedAccountNumbers,
            ]);
            $optimization = app(CashflowOptimizationService::class)->compute($user->id, $activeOwe, $activeReceive, $position, $months);

            $service = app(InsightPayloadService::class);
            if ($request->boolean('scenarios')) {
                $payload = $service->buildWithScenarios($user->id, $position, $activeOwe, $activeReceive, $months, 20_000_000, 15, $linkedAccountNumbers);
            } else {
                $payload = $service->build($position, $projection, $optimization, $activeOwe, $activeReceive, $months);
            }

            $systemPrompt = InsightPayloadService::GPT_SYSTEM_PROMPT;
            $userPrompt = InsightPayloadService::buildUserPrompt($payload);

            return response()->json([
                'system_prompt' => $systemPrompt,
                'user_prompt' => $userPrompt,
                'payload' => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::error('TaiChinhController@insightPayload: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Không tạo được dữ liệu insight. Vui lòng thử lại sau.'], 500);
        }
    }

    /**
     * Dữ liệu view tối thiểu khi load trang tài chính bị lỗi (tránh 500).
     */
    private function minimalViewDataForError(Request $request, string $message): array
    {
        $user = $request->user();
        return [
            'title' => 'Tài chính',
            'load_error' => true,
            'load_error_message' => $message,
            'planExpiringSoon' => false,
            'planExpiresAt' => $user?->plan_expires_at,
            'currentPlan' => $user?->plan,
        ];
    }
}
