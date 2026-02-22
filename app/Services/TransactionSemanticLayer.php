<?php

namespace App\Services;

use App\Data\SemanticTransactionCollection;
use App\DTOs\SemanticTransactionDTO;
use App\Models\TransactionHistory;
use App\Models\User;
use App\Models\UserRecurringPattern;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Transaction Semantic Layer: orchestrate classifier + role + recurring.
 * Chỉ wrap primitives có sẵn, không thay đổi logic, không đổi schema.
 * Downstream đọc SemanticTransactionCollection thay vì raw transaction.
 */
class TransactionSemanticLayer
{
    public function __construct(
        private FinancialRoleClassifier $roleClassifier,
    ) {}

    public function buildSemanticView(User $user, CarbonInterface $from, CarbonInterface $to): SemanticTransactionCollection
    {
        $linkedAccounts = $user->userBankAccounts()
            ->pluck('account_number')
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($linkedAccounts)) {
            return new SemanticTransactionCollection([], $from, $to);
        }

        $transactions = TransactionHistory::with('systemCategory')
            ->where('user_id', $user->id)
            ->where(function ($q) use ($linkedAccounts) {
                $q->whereIn('account_number', $linkedAccounts)
                    ->orWhereHas('bankAccount', fn ($q2) => $q2->whereIn('account_number', $linkedAccounts));
            })
            ->whereBetween('transaction_date', [$from, $to])
            ->orderBy('transaction_date')
            ->get();

        $medianMonthlyAmount = $this->medianMonthlyOutflow($transactions);
        $activePatterns = $this->loadActiveRecurringPatterns($user);

        $dtos = [];
        foreach ($transactions as $t) {
            $roleResult = $this->roleClassifier->classify($t, $medianMonthlyAmount);
            $recurringFlag = $this->matchRecurringReadOnly($t, $activePatterns);
            $classConf = (float) ($t->classification_confidence ?? 0.5);
            $classConf = max(0, min(1, $classConf));
            $roleConf = max(0, min(1, $roleResult['confidence'] ?? 0.5));
            $semanticConf = $this->combineConfidence($classConf, $roleConf);

            $dtos[] = new SemanticTransactionDTO(
                transactionId: $t->id,
                amount: (float) $t->amount,
                type: strtoupper((string) ($t->type ?? 'OUT')),
                financialRole: $roleResult['role'] ?? FinancialRoleClassifier::UNKNOWN,
                merchantGroup: $t->merchant_group ? (string) $t->merchant_group : null,
                systemCategory: $t->systemCategory?->name,
                recurringFlag: $recurringFlag,
                classificationConfidence: $classConf,
                roleConfidence: $roleConf,
                semanticConfidence: $semanticConf,
                transactionDate: $t->transaction_date ? Carbon::parse($t->transaction_date) : null,
            );
        }

        return new SemanticTransactionCollection($dtos, $from, $to);
    }

    private function medianMonthlyOutflow(Collection $transactions): float
    {
        if ($transactions->isEmpty()) {
            return 0.0;
        }
        $byMonth = $transactions->where('type', 'OUT')->groupBy(fn ($t) => Carbon::parse($t->transaction_date)->format('Y-m'));
        $totals = $byMonth->map(fn ($rows) => $rows->sum('amount'))->values()->all();
        if (empty($totals)) {
            return 0.0;
        }
        sort($totals);
        $n = count($totals);
        $mid = (int) floor($n / 2);
        return $n % 2 === 1 ? (float) $totals[$mid] : (float) (($totals[$mid - 1] + $totals[$mid]) / 2);
    }

    /** @param Collection<int, UserRecurringPattern> $patterns */
    private function matchRecurringReadOnly(TransactionHistory $t, Collection $patterns): bool
    {
        $group = $t->merchant_group ? (string) $t->merchant_group : '';
        if ($group === '') {
            return false;
        }
        $txDate = $t->transaction_date ? Carbon::parse($t->transaction_date) : now();
        $amount = (float) $t->amount;
        foreach ($patterns as $pattern) {
            if ($pattern->merchant_group !== $group || $pattern->direction !== $t->type) {
                continue;
            }
            if ($pattern->matchesTransaction($amount, $txDate)) {
                return true;
            }
        }
        return false;
    }

    private function loadActiveRecurringPatterns(User $user): Collection
    {
        $cfg = config('classification.recurring', []);
        $threshold = (float) ($cfg['match_confidence_threshold'] ?? 0.5);

        return UserRecurringPattern::where('user_id', $user->id)
            ->where('status', UserRecurringPattern::STATUS_ACTIVE)
            ->where('confidence_score', '>=', $threshold)
            ->whereNotNull('user_category_id')
            ->get();
    }

    private function combineConfidence(float $classificationConfidence, float $roleConfidence): float
    {
        return (float) round(min($classificationConfidence, $roleConfidence) * 100) / 100;
    }
}
