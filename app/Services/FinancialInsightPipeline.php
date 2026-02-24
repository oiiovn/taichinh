<?php

namespace App\Services;

use App\DTOs\InsightResult;
use App\Models\FinancialStateSnapshot;
use App\Models\User;
use Carbon\Carbon;

class FinancialInsightPipeline
{
    public function __construct(
        private DataSufficiencyService $dataSufficiencyService,
        private UserStrategyProfileService $strategyProfileService,
        private CashflowProjectionService $projectionService,
        private FinancialStateClassificationService $stateClassifier,
        private FinancialConsistencyResolver $resolver,
        private ContextualFramingService $framingService,
        private ObjectiveAwarenessService $objectiveService,
        private CashflowOptimizationService $optimizationService,
        private FinancialNarrativeBuilder $narrativeBuilder,
        private InsightPayloadService $insightPayloadService,
        private TransactionSemanticLayer $semanticLayer,
        private BehavioralProfileService $behavioralProfileService,
        private DriftAnalyzerService $driftAnalyzer,
        private UserNarrativeMemoryBuilder $narrativeMemoryBuilder,
        private CognitiveSynthesisService $cognitiveService,
        private DualAxisAwarenessService $dualAxisService,
        private EconomicContextService $economicContextService,
        private DecisionCoreService $decisionCoreService,
        private RealityGuardService $realityGuardService,
        private BudgetThresholdService $budgetThresholdService,
        private BudgetIntelligenceService $budgetIntelligenceService,
        private MetaBudgetCognitionService $metaBudgetCognitionService,
        private IncomeGoalService $incomeGoalService,
        private InsightHashService $insightHashService,
    ) {}

    public function run(User $user, array $context = []): InsightResult
    {
        $position = $context['position'] ?? [];
        $oweItems = $context['oweItems'] ?? collect();
        $receiveItems = $context['receiveItems'] ?? collect();
        $currentAccountCount = (int) ($context['currentAccountCount'] ?? 0);
        $unifiedLoansCount = $oweItems->count() + $receiveItems->count();

        $linkedAccountNumbers = $context['linkedAccountNumbers'] ?? [];
        $dataSufficiency = $this->dataSufficiencyService->check(
            $user->id,
            $currentAccountCount,
            $unifiedLoansCount,
            $linkedAccountNumbers
        );
        $insufficientData = ! ($dataSufficiency['sufficient'] ?? true);
        $onboardingNarrative = $dataSufficiency['onboarding_narrative'] ?? null;

        $out = array_merge($context, [
            'dataSufficiency' => $dataSufficiency,
            'insufficientData' => $insufficientData,
            'onboardingNarrative' => $onboardingNarrative,
            'projection' => null,
            'projectionOptimization' => null,
            'financialState' => null,
            'priorityMode' => null,
            'contextualFrame' => null,
            'objective' => null,
            'narrativeResult' => null,
            'insightPayload' => null,
            'insightHash' => null,
        ]);

        if ($insufficientData) {
            $out['financialState'] = DataSufficiencyService::insufficientDataState();
            $out['priorityMode'] = [
                'key' => 'defensive',
                'label' => 'Chế độ phòng thủ',
                'description' => 'Chưa đủ dữ liệu — ưu tiên tích lũy dữ liệu trước.',
            ];
            $out['dualAxis'] = $this->dualAxisService->compute(
                $dataSufficiency,
                $insufficientData,
                $out['financialState'],
                $out['priorityMode']
            );
            return new InsightResult($out);
        }

        $strategyProfile = $this->strategyProfileService->getProfile($user->id);
        $months = (int) ($context['projection_months'] ?? 12);
        $scenario = array_filter([
            'extra_income_per_month' => $context['extra_income_per_month'] ?? null,
            'expense_reduction_pct' => $context['expense_reduction_pct'] ?? null,
            'extra_payment_loan_id' => $context['extra_payment_loan_id'] ?? null,
            'extra_payment_amount' => $context['extra_payment_amount'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
        $activeOwe = $oweItems->where('is_active', true)->values();
        $activeReceive = $receiveItems->where('is_active', true)->values();

        $semanticFrom = Carbon::now()->subMonths(max($months, 12));
        $semanticTo = Carbon::now();
        $semanticView = $this->semanticLayer->buildSemanticView($user, $semanticFrom, $semanticTo);
        $economicContext = $this->economicContextService->compute($semanticView);

        $lastSnapshotWithError = FinancialStateSnapshot::where('user_id', $user->id)
            ->whereNotNull('forecast_error')
            ->orderBy('snapshot_date', 'desc')
            ->first();
        $forecastErrorHigh = $lastSnapshotWithError !== null && (float) $lastSnapshotWithError->forecast_error > 0.25;

        $projection = $this->projectionService->run(
            $user->id,
            $activeOwe,
            $activeReceive,
            $position,
            $months,
            $scenario,
            [
                'economic_context' => $economicContext,
                'forecast_error_high' => $forecastErrorHigh,
                'linked_account_count' => $currentAccountCount,
                'linked_account_numbers' => $linkedAccountNumbers,
            ]
        );
        $financialStateRaw = $this->stateClassifier->classify($position, $projection);
        $priorityModeRaw = $this->stateClassifier->classifyPriorityMode($position, $projection, $financialStateRaw);
        $resolved = $this->resolver->resolve($financialStateRaw, $priorityModeRaw, $position, $projection);
        $financialState = $resolved['resolved_state'];
        $priorityMode = $resolved['resolved_mode'];
        $contextualFrame = $this->framingService->frame($position, $projection, $priorityMode, $financialState);
        $objective = $this->objectiveService->infer($financialState, $priorityMode, $position, $projection, $activeOwe);
        $behaviorProfileRow = \App\Models\UserBehaviorProfile::where('user_id', $user->id)->first();
        $behavioralScores = [];
        if ($behaviorProfileRow) {
            $behavioralScores = [
                'execution_consistency_score_reduce_expense' => $behaviorProfileRow->execution_consistency_score_reduce_expense !== null ? (float) $behaviorProfileRow->execution_consistency_score_reduce_expense : 100,
                'execution_consistency_score_debt' => $behaviorProfileRow->execution_consistency_score_debt !== null ? (float) $behaviorProfileRow->execution_consistency_score_debt : null,
                'execution_consistency_score_income' => $behaviorProfileRow->execution_consistency_score_income !== null ? (float) $behaviorProfileRow->execution_consistency_score_income : null,
                'execution_consistency_score' => $behaviorProfileRow->execution_consistency_score !== null ? (float) $behaviorProfileRow->execution_consistency_score : 50,
            ];
            $strategyProfile['execution_consistency_score_reduce_expense'] = $behaviorProfileRow->execution_consistency_score_reduce_expense;
            $strategyProfile['execution_consistency_score_debt'] = $behaviorProfileRow->execution_consistency_score_debt;
            $strategyProfile['execution_consistency_score_income'] = $behaviorProfileRow->execution_consistency_score_income;
            $strategyProfile['execution_consistency_score'] = $behaviorProfileRow->execution_consistency_score;
        }
        $projectionOptimization = $this->optimizationService->compute(
            $user->id,
            $activeOwe,
            $activeReceive,
            $position,
            $months,
            $strategyProfile,
            $financialState,
            $priorityMode,
            $contextualFrame,
            $objective,
            $behavioralScores
        );
        $narrativeResult = $this->narrativeBuilder->build(
            $financialState,
            $priorityMode,
            $projection,
            $projectionOptimization['root_causes'] ?? [],
            $projectionOptimization['strategic_guidance']['guidance_lines'] ?? []
        );
        $insightPayload = $this->insightPayloadService->build(
            $position,
            $projection,
            $projectionOptimization,
            $activeOwe,
            $activeReceive,
            $months,
            $strategyProfile,
            $economicContext
        );
        $canonical = $projection['sources']['canonical'] ?? [];
        $behaviorProfile = $this->behavioralProfileService->compute($user, $canonical, $semanticView);
        $insightPayload = $this->behavioralProfileService->injectIntoPayload($insightPayload, $behaviorProfile);

        $debtIntelligence = $insightPayload['debt_intelligence'] ?? [];
        $sources = $projection['sources'] ?? [];
        $currentState = $this->buildCurrentStateForSnapshot(
            $position,
            $canonical,
            $financialState,
            $objective,
            $debtIntelligence,
            $behaviorProfile,
            $strategyProfile,
            $sources
        );
        $snapshots = $this->driftAnalyzer->loadLastSnapshots($user->id);
        $driftSignals = $this->driftAnalyzer->analyze($currentState, $snapshots);
        $insightPayload = $this->injectDriftIntoPayload($insightPayload, $driftSignals);
        $insightPayload = $this->injectEconomicContext($insightPayload, $economicContext);

        $linkedAccountNumbers = $context['linkedAccountNumbers'] ?? [];
        $thresholdSummary = $this->budgetThresholdService->getThresholdSummaryForUser($user->id, $linkedAccountNumbers);
        $budgetContext = [
            'debt_stress_index' => isset($debtIntelligence['debt_stress_index']) ? (int) $debtIntelligence['debt_stress_index'] : null,
            'surplus_positive' => isset($canonical['free_cashflow_after_debt']) ? (float) $canonical['free_cashflow_after_debt'] >= -500_000 : null,
            'recommended_surplus_retention_pct' => null,
        ];
        $budgetIntelligence = $this->budgetIntelligenceService->compute($user->id, $linkedAccountNumbers, $thresholdSummary, $budgetContext);
        $incomeGoalSummary = $this->incomeGoalService->getGoalSummaryForUser($user->id, $linkedAccountNumbers);
        $cognitive = $insightPayload['cognitive_input'] ?? [];
        $cognitive['threshold_summary'] = $thresholdSummary;
        $cognitive['budget_intelligence'] = $budgetIntelligence;
        $cognitive['meta_budget_cognition'] = $this->metaBudgetCognitionService->compute($thresholdSummary, $budgetIntelligence, $budgetContext);
        $cognitive['income_goal_summary'] = $incomeGoalSummary;
        $insightPayload['cognitive_input'] = $cognitive;

        $userParams = $this->loadUserBrainParams($user->id);
        $decisionCore = $this->decisionCoreService->compute(
            $financialState,
            $priorityMode,
            $contextualFrame,
            $canonical,
            $projectionOptimization,
            $economicContext,
            $driftSignals,
            $behavioralScores,
            $objective,
            $userParams,
            $thresholdSummary,
            $budgetIntelligence
        );
        $insightPayload = $this->injectDecisionCore($insightPayload, $decisionCore);

        $decisionBundle = $insightPayload['cognitive_input']['decision_bundle'] ?? null;
        $canonicalSources = $projection['sources']['canonical'] ?? [];
        $thu = (float) ($canonicalSources['projected_income'] ?? $sources['projected_income'] ?? $sources['recurring_income'] ?? 0);
        $outflowPerMonth = (float) ($sources['behavior_expense'] ?? 0) + (float) ($sources['recurring_expense'] ?? 0);
        $loanSchedule = (float) ($sources['loan_schedule'] ?? 0);
        $outflowPerMonth += $months > 0 ? $loanSchedule / $months : 0;
        $projectionOptimization = $this->realityGuardService->sanitizeOptimization(
            $projectionOptimization,
            $decisionBundle,
            $thu,
            $outflowPerMonth
        );
        $insightPayload['debt_intelligence'] = $this->realityGuardService->sanitizeDebtIntelligence(
            $insightPayload['debt_intelligence'] ?? []
        );
        if (isset($insightPayload['cognitive_input']['optimization_snapshot'])) {
            $insightPayload['cognitive_input']['optimization_snapshot'] = array_merge(
                $insightPayload['cognitive_input']['optimization_snapshot'] ?? [],
                ['min_expense_reduction_pct' => $projectionOptimization['min_expense_reduction_pct'] ?? null]
            );
        }

        $survivalCheck = $this->realityGuardService->checkSurvivalProtocol(
            $position,
            $projection,
            $insightPayload['debt_intelligence'] ?? [],
            $priorityMode
        );
        $survivalProtocolActive = $survivalCheck['active'] ?? false;
        if ($survivalProtocolActive && ! empty($survivalCheck['directive'])) {
            $insightPayload['survival_protocol_active'] = true;
            $insightPayload['survival_directive'] = $survivalCheck['directive'];
        }

        $currentStateForNarrative = array_merge($currentState, [
            'brain_mode_key' => $insightPayload['cognitive_input']['brain_mode']['key'] ?? null,
        ]);
        $behaviorProfileArray = $insightPayload['cognitive_input']['behavioral_profile'] ?? [];
        $narrativeMemory = $this->narrativeMemoryBuilder->build(
            $user,
            $snapshots,
            $driftSignals,
            $strategyProfile,
            $behaviorProfileArray,
            $currentStateForNarrative
        );
        $insightPayload = $this->injectNarrativeMemory($insightPayload, $narrativeMemory);

        $forceRefresh = ! empty($context['refresh_insight']);
        $narrativeConfidence = (float) ($narrativeResult['narrative_confidence'] ?? 0) / 100.0;
        if ($forecastErrorHigh) {
            $narrativeConfidence *= 0.8;
        }
        $cognitiveNarrative = $this->cognitiveService->synthesize(
            $user->id,
            $narrativeResult,
            $insightPayload,
            $narrativeConfidence,
            $forceRefresh
        );
        if ($cognitiveNarrative !== null && $cognitiveNarrative !== '') {
            $narrativeResult['narrative'] = $cognitiveNarrative;
        }
        $insightHash = $this->insightHashService->compute($position, $projectionOptimization);

        $brainModeKey = $insightPayload['cognitive_input']['brain_mode']['key'] ?? null;
        $decisionBundle = $insightPayload['cognitive_input']['decision_bundle'] ?? null;
        $this->saveSnapshot($user->id, $currentState, $insightHash, $brainModeKey, $decisionBundle);
        $dualAxis = $this->dualAxisService->compute(
            $dataSufficiency,
            $insufficientData,
            $financialState,
            $priorityMode
        );

        $out = array_merge($context, [
            'dataSufficiency' => $dataSufficiency,
            'insufficientData' => $insufficientData,
            'onboardingNarrative' => $onboardingNarrative,
            'projection' => $projection,
            'projectionOptimization' => $projectionOptimization,
            'oweItemsForProjection' => $oweItems,
            'financialState' => $financialState,
            'priorityMode' => $priorityMode,
            'contextualFrame' => $contextualFrame,
            'objective' => $objective,
            'narrativeResult' => $narrativeResult,
            'insightPayload' => $insightPayload,
            'insightHash' => $insightHash,
            'dualAxis' => $dualAxis,
            'survival_protocol_active' => $survivalProtocolActive,
        ]);

        return new InsightResult($out);
    }

    /**
     * @param  array<string, mixed>  $position
     * @param  array<string, mixed>  $canonical
     * @param  array<string, mixed>  $debtIntelligence
     * @param  array<string, mixed>  $strategyProfile
     * @param  array<string, mixed>  $sources  projection sources (projected_income, behavior_expense, recurring_expense) cho forecast learning
     */
    private function buildCurrentStateForSnapshot(
        array $position,
        array $canonical,
        ?array $financialState,
        ?array $objective,
        array $debtIntelligence,
        \App\DTOs\BehavioralProfileDTO $behaviorProfile,
        array $strategyProfile,
        array $sources = []
    ): array {
        $runwayMonths = $canonical['runway_from_liquidity_months'] ?? null;
        $recommended = (int) ($canonical['required_runway_months'] ?? 0) ?: null;
        $projIncome = (float) ($sources['projected_income'] ?? $sources['recurring_income'] ?? 0);
        $projExpense = (float) ($sources['behavior_expense'] ?? 0) + (float) ($sources['recurring_expense'] ?? 0);
        return [
            'structural_state' => $financialState,
            'buffer_months' => $runwayMonths !== null ? (int) $runwayMonths : null,
            'recommended_buffer' => $recommended,
            'liquidity_status' => (string) ($canonical['liquidity_status'] ?? 'positive'),
            'dsi' => isset($debtIntelligence['debt_stress_index']) ? (int) $debtIntelligence['debt_stress_index'] : null,
            'debt_exposure' => (float) ($position['debt_exposure'] ?? 0),
            'receivable_exposure' => (float) ($position['receivable_exposure'] ?? 0),
            'net_leverage' => (float) ($position['net_leverage'] ?? 0),
            'income_volatility' => isset($canonical['volatility_ratio']) ? (float) $canonical['volatility_ratio'] : null,
            'spending_discipline_score' => $behaviorProfile->spendingDisciplineScore,
            'execution_consistency_score' => $behaviorProfile->executionConsistencyScore,
            'objective' => $objective,
            'priority_alignment' => $debtIntelligence['priority_alignment'] ?? null,
            'total_feedback_count' => (int) ($strategyProfile['total_feedback_count'] ?? 0),
            'projected_income_monthly' => $projIncome > 0 ? round($projIncome, 2) : null,
            'projected_expense_monthly' => $projExpense > 0 ? round($projExpense, 2) : null,
        ];
    }

    private function injectDriftIntoPayload(array $payload, array $driftSignals): array
    {
        $cognitive = $payload['cognitive_input'] ?? [];
        $cognitive['drift_signals'] = $driftSignals;
        $payload['cognitive_input'] = $cognitive;
        return $payload;
    }

    private function injectEconomicContext(array $payload, array $economicContext): array
    {
        $cognitive = $payload['cognitive_input'] ?? [];
        $cognitive['economic_context'] = $economicContext;
        if (strtolower((string) ($economicContext['platform_dependency'] ?? '')) === 'high') {
            $cognitive['recommended_surplus_retention_pct'] = 40;
        }
        $payload['cognitive_input'] = $cognitive;
        return $payload;
    }

    private function loadUserBrainParams(int $userId): ?array
    {
        try {
            $rows = \App\Models\UserBrainParam::where('user_id', $userId)->get();
        } catch (\Throwable) {
            return null;
        }
        if ($rows->isEmpty()) {
            return null;
        }
        $out = [];
        foreach ($rows as $r) {
            $out[$r->param_key] = $r->param_value;
        }
        return $out;
    }

    private function injectDecisionCore(array $payload, array $decisionCore): array
    {
        $cognitive = $payload['cognitive_input'] ?? [];
        $cognitive['decision_bundle'] = $decisionCore['decision_bundle'] ?? [];
        $cognitive['brain_mode'] = $decisionCore['brain_mode'] ?? [];
        if (! empty($cognitive['brain_mode']['narrative_blocks'])) {
            $cognitive['recommended_surplus_retention_pct'] = $decisionCore['decision_bundle']['surplus_retention_pct'] ?? $cognitive['recommended_surplus_retention_pct'] ?? null;
        }
        $payload['cognitive_input'] = $cognitive;
        return $payload;
    }

    private function injectNarrativeMemory(array $payload, array $narrativeMemory): array
    {
        $cognitive = $payload['cognitive_input'] ?? [];
        $cognitive['narrative_memory'] = $narrativeMemory['narrative_memory'] ?? [];
        $payload['cognitive_input'] = $cognitive;
        return $payload;
    }

    /**
     * Cấp 3 – Snapshot theo State Change: chỉ tạo khi state thay đổi có ý nghĩa.
     *
     * @param  array<string, mixed>  $currentState
     * @param  array<string, mixed>|null  $decisionBundle
     */
    private function saveSnapshot(int $userId, array $currentState, string $narrativeHash, ?string $brainModeKey = null, ?array $decisionBundle = null): void
    {
        $last = FinancialStateSnapshot::where('user_id', $userId)
            ->latest('created_at')
            ->first();

        if ($last && ! $this->hasMeaningfulStateChange($last, $currentState, $brainModeKey)) {
            return;
        }

        FinancialStateSnapshot::create([
            'user_id' => $userId,
            'snapshot_date' => Carbon::today(),
            'structural_state' => $currentState['structural_state'],
            'buffer_months' => $currentState['buffer_months'],
            'recommended_buffer' => $currentState['recommended_buffer'],
            'liquidity_status' => $currentState['liquidity_status'] ?? null,
            'dsi' => $currentState['dsi'],
            'debt_exposure' => $currentState['debt_exposure'],
            'receivable_exposure' => $currentState['receivable_exposure'],
            'net_leverage' => $currentState['net_leverage'],
            'income_volatility' => $currentState['income_volatility'],
            'spending_discipline_score' => $currentState['spending_discipline_score'],
            'execution_consistency_score' => $currentState['execution_consistency_score'] ?? null,
            'objective' => $currentState['objective'],
            'priority_alignment' => $currentState['priority_alignment'],
            'narrative_hash' => $narrativeHash,
            'brain_mode_key' => $brainModeKey,
            'decision_bundle_snapshot' => $decisionBundle,
            'total_feedback_count' => (int) ($currentState['total_feedback_count'] ?? 0),
            'projected_income_monthly' => $currentState['projected_income_monthly'] ?? null,
            'projected_expense_monthly' => $currentState['projected_expense_monthly'] ?? null,
        ]);
    }

    /**
     * Có thay đổi state có ý nghĩa so với snapshot gần nhất (structural_state, brain_mode, buffer, DSI, liquidity_status).
     */
    private function hasMeaningfulStateChange(FinancialStateSnapshot $last, array $currentState, ?string $brainModeKey): bool
    {
        $lastStateKey = is_array($last->structural_state) ? ($last->structural_state['key'] ?? null) : null;
        $curStateKey = isset($currentState['structural_state']) && is_array($currentState['structural_state'])
            ? ($currentState['structural_state']['key'] ?? null) : null;
        if ($lastStateKey !== $curStateKey) {
            return true;
        }

        if ($last->brain_mode_key !== $brainModeKey) {
            return true;
        }

        $lastBuf = $last->buffer_months !== null ? (float) $last->buffer_months : null;
        $curBuf = $currentState['buffer_months'] ?? null;
        if ($lastBuf !== null && $curBuf !== null) {
            if (abs($lastBuf - (float) $curBuf) >= 0.5) {
                return true;
            }
        } elseif ($lastBuf !== $curBuf) {
            return true;
        }

        $lastDsi = $last->dsi !== null ? (int) $last->dsi : null;
        $curDsi = isset($currentState['dsi']) ? (int) $currentState['dsi'] : null;
        if ($lastDsi !== null && $curDsi !== null) {
            if (abs($lastDsi - $curDsi) >= 5) {
                return true;
            }
        } elseif ($lastDsi !== $curDsi) {
            return true;
        }

        $lastLiq = (string) ($last->liquidity_status ?? '');
        $curLiq = (string) ($currentState['liquidity_status'] ?? '');
        if ($lastLiq !== $curLiq) {
            return true;
        }

        return false;
    }
}
