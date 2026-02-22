<?php

namespace App\Services;

use App\Data\SemanticTransactionCollection;
use App\DTOs\BehavioralProfileDTO;
use App\Models\BehaviorLog;
use App\Models\TransactionHistory;
use App\Models\User;
use App\Models\UserBehaviorProfile;

class BehavioralProfileService
{
    /**
     * @param  array<string, mixed>  $canonical
     * Khi truyền $semantic: đọc semantic view (nguồn sự thật thống nhất). Không truyền thì fallback raw transaction.
     */
    public function compute(User $user, array $canonical, ?SemanticTransactionCollection $semantic = null): BehavioralProfileDTO
    {
        $profile = UserBehaviorProfile::where('user_id', $user->id)->first();
        $signals = $semantic !== null
            ? $this->getSignalsFromSemantic($semantic)
            : $this->getSignalsFromTransactions($user);
        $this->mergeSignalsFromLogs($user, $signals);

        $debtStyle = $profile?->debt_style ?? UserBehaviorProfile::DEBT_STYLE_UNKNOWN;
        $riskTolerance = $profile?->risk_tolerance ?? 'medium';
        $spendingRaw = $profile ? (float) $profile->spending_discipline_score : 0.5;
        $executionRaw = $profile ? (float) $profile->execution_consistency_score : 0.5;
        $rawSurplus = $profile?->surplus_usage_pattern ?? 'mixed';
        $surplusUsage = in_array($rawSurplus, ['spend', 'save', 'invest', 'mixed'], true)
            ? $rawSurplus
            : (str_contains(strtolower($rawSurplus ?? ''), 'save') ? 'save' : 'mixed');

        $spendingDisciplineScore = $spendingRaw > 1 ? $spendingRaw / 100.0 : $spendingRaw;
        $executionConsistencyScore = $executionRaw > 1 ? $executionRaw / 100.0 : $executionRaw;
        $spendingDisciplineScore = max(0, min(1, $spendingDisciplineScore));
        $executionConsistencyScore = max(0, min(1, $executionConsistencyScore));

        $scoreReduceExpense = $profile && $profile->execution_consistency_score_reduce_expense !== null
            ? (float) $profile->execution_consistency_score_reduce_expense : null;
        $scoreDebt = $profile && $profile->execution_consistency_score_debt !== null
            ? (float) $profile->execution_consistency_score_debt : null;
        $scoreIncome = $profile && $profile->execution_consistency_score_income !== null
            ? (float) $profile->execution_consistency_score_income : null;

        if ($profile && in_array($profile->risk_underestimation_flag, ['yes', '1', 1], true)) {
            $signals['risk_underestimation_flag'] = true;
        }

        return new BehavioralProfileDTO(
            debtStyle: $debtStyle,
            riskTolerance: $riskTolerance,
            spendingDisciplineScore: $spendingDisciplineScore,
            executionConsistencyScore: $executionConsistencyScore,
            surplusUsagePattern: $surplusUsage,
            behaviorSignals: $signals,
            executionConsistencyScoreReduceExpense: $scoreReduceExpense,
            executionConsistencyScoreDebt: $scoreDebt,
            executionConsistencyScoreIncome: $scoreIncome,
        );
    }

    public function injectIntoPayload(array $payload, BehavioralProfileDTO $profile): array
    {
        $cognitive = $payload['cognitive_input'] ?? [];
        $cognitive['behavioral_profile'] = $profile->toPayloadArray();
        $payload['cognitive_input'] = $cognitive;

        return $payload;
    }

    private function getSignalsFromTransactions(User $user): array
    {
        $signals = [
            'income_elastic_spender' => false,
            'debt_priority_mismatch' => false,
            'lifestyle_inflation_flag' => false,
        ];

        $linkedAccounts = $user->userBankAccounts()->pluck('account_number')->map(fn ($n) => trim((string) $n))->filter()->unique()->values()->all();
        if (empty($linkedAccounts)) {
            return $signals;
        }

        $tx = TransactionHistory::where('user_id', $user->id)
            ->where(function ($q) use ($linkedAccounts) {
                $q->whereIn('account_number', $linkedAccounts)
                    ->orWhereHas('bankAccount', fn ($q2) => $q2->whereIn('account_number', $linkedAccounts));
            })
            ->orderBy('transaction_date', 'desc')
            ->limit(500)
            ->get();

        if ($tx->isEmpty()) {
            return $signals;
        }

        $byMonth = $tx->groupBy(fn ($r) => \Carbon\Carbon::parse($r->transaction_date)->format('Y-m'));
        $months = $byMonth->keys()->sort()->values()->all();
        if (count($months) < 2) {
            return $signals;
        }

        $incomeByMonth = [];
        $expenseByMonth = [];
        foreach ($byMonth as $month => $rows) {
            $incomeByMonth[$month] = $rows->where('type', 'IN')->sum('amount');
            $expenseByMonth[$month] = $rows->where('type', 'OUT')->sum('amount');
        }

        $lastIncome = $incomeByMonth[$months[count($months) - 1]] ?? 0;
        $prevIncome = $incomeByMonth[$months[count($months) - 2]] ?? 0;
        $lastExpense = $expenseByMonth[$months[count($months) - 1]] ?? 0;
        $prevExpense = $expenseByMonth[$months[count($months) - 2]] ?? 0;
        if ($prevIncome > 0 && $lastIncome > $prevIncome * 1.1) {
            $expenseGrowth = $prevExpense > 0 ? ($lastExpense - $prevExpense) / $prevExpense : 0;
            if ($expenseGrowth > 0.05) {
                $signals['income_elastic_spender'] = true;
            }
        }
        if ($prevIncome > 0 && $lastIncome > $prevIncome * 1.15 && ($lastExpense - $prevExpense) / max(1, $prevExpense) > 0.1) {
            $signals['lifestyle_inflation_flag'] = true;
        }

        return $signals;
    }

    private function getSignalsFromSemantic(SemanticTransactionCollection $semantic): array
    {
        $signals = [
            'income_elastic_spender' => false,
            'debt_priority_mismatch' => false,
            'lifestyle_inflation_flag' => false,
        ];
        $incomeByMonth = $semantic->incomeByMonth();
        $expenseByMonth = $semantic->expenseByMonth();
        $months = array_keys($incomeByMonth + $expenseByMonth);
        sort($months);
        if (count($months) < 2) {
            return $signals;
        }
        $lastIncome = $incomeByMonth[$months[count($months) - 1]] ?? 0;
        $prevIncome = $incomeByMonth[$months[count($months) - 2]] ?? 0;
        $lastExpense = $expenseByMonth[$months[count($months) - 1]] ?? 0;
        $prevExpense = $expenseByMonth[$months[count($months) - 2]] ?? 0;
        if ($prevIncome > 0 && $lastIncome > $prevIncome * 1.1) {
            $expenseGrowth = $prevExpense > 0 ? ($lastExpense - $prevExpense) / $prevExpense : 0;
            if ($expenseGrowth > 0.05) {
                $signals['income_elastic_spender'] = true;
            }
        }
        if ($prevIncome > 0 && $lastIncome > $prevIncome * 1.15 && ($lastExpense - $prevExpense) / max(1, $prevExpense) > 0.1) {
            $signals['lifestyle_inflation_flag'] = true;
        }
        return $signals;
    }

    private function mergeSignalsFromLogs(User $user, array &$signals): void
    {
        $recent = BehaviorLog::where('user_id', $user->id)
            ->orderBy('logged_at', 'desc')
            ->limit(50)
            ->get();
        $rejected = $recent->where('accepted', false)->count();
        if ($recent->count() >= 5 && $rejected >= 3) {
            $signals['debt_priority_mismatch'] = true;
        }
    }

}
