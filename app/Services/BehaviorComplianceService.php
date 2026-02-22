<?php

namespace App\Services;

use App\Models\BehaviorLog;
use App\Models\TransactionHistory;
use App\Models\User;
use App\Models\UserBehaviorProfile;
use App\Models\UserBrainParam;
use Carbon\Carbon;

/**
 * So sánh đề xuất đã đồng ý vs hành vi thực tế sau ~30 ngày.
 * Score có decay theo thời gian (exp(-λ * age_days)); tách score theo suggestion_type.
 */
class BehaviorComplianceService
{
    private const DAYS_AFTER = 30;

    private const DAYS_BEFORE = 30;

    /** λ cho decay: weight = exp(-λ * age_days). Half-life ~69 ngày khi λ=0.01 */
    private const DECAY_LAMBDA = 0.01;

    /** Số log gần nhất dùng cho weighted score (không cắt theo ngày). */
    private const MAX_LOGS = 50;

    /** suggestion_type → bucket cho score theo loại */
    private const BUCKET_REDUCE_EXPENSE = ['reduce_expense'];

    private const BUCKET_INCOME = ['increase_income', 'insight_agree'];

    private const BUCKET_DEBT = ['no_more_borrow', 'pay_debt'];

    private const MEASURABLE_TYPES = [
        'reduce_expense',
        'increase_income',
        'insight_agree',
    ];

    public function runForAllUsers(): void
    {
        $cutoff = Carbon::now()->subDays(self::DAYS_AFTER);
        $logs = BehaviorLog::where('accepted', true)
            ->whereNull('action_taken')
            ->where('logged_at', '<=', $cutoff)
            ->whereIn('suggestion_type', self::MEASURABLE_TYPES)
            ->orderBy('logged_at')
            ->get();

        foreach ($logs as $log) {
            $this->evaluateAndUpdate($log);
        }

        foreach ($logs->pluck('user_id')->unique() as $userId) {
            $this->updateExecutionConsistencyScores($userId);
        }
    }

    public function evaluateAndUpdate(BehaviorLog $log): void
    {
        $user = User::find($log->user_id);
        if (! $user) {
            return;
        }

        $taken = $this->evaluateCompliance($user, $log);
        $log->update(['action_taken' => $taken]);
    }

    public function updateExecutionConsistencyScores(int $userId): void
    {
        $recent = BehaviorLog::where('user_id', $userId)
            ->where('accepted', true)
            ->whereNotNull('action_taken')
            ->orderBy('logged_at', 'desc')
            ->limit(self::MAX_LOGS)
            ->get();

        if ($recent->isEmpty()) {
            return;
        }

        $now = Carbon::now();
        $weightedSum = 0.0;
        $weightTotal = 0.0;
        $byBucket = [
            'reduce_expense' => ['weighted_compliant' => 0.0, 'weighted_total' => 0.0],
            'income' => ['weighted_compliant' => 0.0, 'weighted_total' => 0.0],
            'debt' => ['weighted_compliant' => 0.0, 'weighted_total' => 0.0],
        ];

        foreach ($recent as $log) {
            $ageDays = (Carbon::parse($log->logged_at))->diffInDays($now);
            $weight = exp(-self::DECAY_LAMBDA * $ageDays);
            $compliant = $log->action_taken ? 1.0 : 0.0;
            $weightedSum += $weight * $compliant;
            $weightTotal += $weight;

            $bucket = $this->suggestionTypeToBucket($log->suggestion_type);
            if ($bucket !== null) {
                $byBucket[$bucket]['weighted_compliant'] += $weight * $compliant;
                $byBucket[$bucket]['weighted_total'] += $weight;
            }
        }

        $score = $weightTotal > 0 ? round(100.0 * $weightedSum / $weightTotal, 2) : null;
        $scoreReduceExpense = $this->bucketScore($byBucket['reduce_expense']);
        $scoreIncome = $this->bucketScore($byBucket['income']);
        $scoreDebt = $this->bucketScore($byBucket['debt']);

        UserBehaviorProfile::updateOrCreate(
            ['user_id' => $userId],
            [
                'execution_consistency_score' => $score,
                'execution_consistency_score_reduce_expense' => $scoreReduceExpense,
                'execution_consistency_score_debt' => $scoreDebt,
                'execution_consistency_score_income' => $scoreIncome,
            ]
        );

        $this->upsertBehaviorMismatchFlag($userId, $scoreReduceExpense);
    }

    /** Learning Loop: compliance giảm chi thấp → flag để BrainMode/DecisionCore điều chỉnh đề xuất. */
    private function upsertBehaviorMismatchFlag(int $userId, ?float $scoreReduceExpense): void
    {
        try {
            $value = ($scoreReduceExpense !== null && $scoreReduceExpense < 40) ? 1 : 0;
            UserBrainParam::updateOrCreate(
                ['user_id' => $userId, 'param_key' => 'expense_suggestion_soften'],
                ['param_value' => $value]
            );
        } catch (\Throwable) {
            // Bảng user_brain_params có thể chưa có
        }
    }

    private function bucketScore(array $bucket): ?float
    {
        if ($bucket['weighted_total'] <= 0) {
            return null;
        }
        return round(100.0 * $bucket['weighted_compliant'] / $bucket['weighted_total'], 2);
    }

    private function suggestionTypeToBucket(string $suggestionType): ?string
    {
        if (in_array($suggestionType, self::BUCKET_REDUCE_EXPENSE, true)) {
            return 'reduce_expense';
        }
        if (in_array($suggestionType, self::BUCKET_INCOME, true)) {
            return 'income';
        }
        if (in_array($suggestionType, self::BUCKET_DEBT, true)) {
            return 'debt';
        }
        return null;
    }

    private function evaluateCompliance(User $user, BehaviorLog $log): bool
    {
        $beforeStart = Carbon::parse($log->logged_at)->subDays(self::DAYS_BEFORE);
        $beforeEnd = $log->logged_at;
        $afterStart = $log->logged_at;
        $afterEnd = Carbon::parse($log->logged_at)->addDays(self::DAYS_AFTER);

        $before = $this->aggregateByPeriod($user->id, $beforeStart, $beforeEnd);
        $after = $this->aggregateByPeriod($user->id, $afterStart, $afterEnd);

        return match ($log->suggestion_type) {
            'reduce_expense' => $after['out'] <= $before['out'] * 1.05,
            'increase_income' => $after['in'] >= $before['in'] * 0.95 && $after['in'] > 0,
            'insight_agree' => ($before['in'] - $before['out']) <= ($after['in'] - $after['out']) + 1,
            default => false,
        };
    }

    /** @return array{in: float, out: float} */
    private function aggregateByPeriod(int $userId, Carbon $start, Carbon $end): array
    {
        $rows = TransactionHistory::where('user_id', $userId)
            ->whereBetween('transaction_date', [$start, $end])
            ->selectRaw('type, COALESCE(SUM(ABS(amount)), 0) as total')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        return [
            'in' => (float) ($rows->get('IN')->total ?? 0),
            'out' => (float) ($rows->get('OUT')->total ?? 0),
        ];
    }
}
