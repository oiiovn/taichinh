<?php

namespace App\Services;

use App\Models\TransactionHistory;
use Carbon\Carbon;

/**
 * Expense Purity: chỉ OPERATING_EXPENSE cho chi tiêu hành vi.
 * OUT có thể là trả nợ / chuyển nội bộ / đầu tư → không đưa vào operating expense.
 */
class ExpensePurityService
{
    public function __construct(
        protected FinancialRoleClassifier $roleClassifier
    ) {}

    /**
     * Chuỗi chi operating theo tháng: SUM(|amount| * score) với role = OPERATING_EXPENSE.
     * Chỉ giao dịch thuộc tài khoản đang liên kết khi linkedAccountNumbers không rỗng.
     *
     * @param  array<string>  $linkedAccountNumbers
     * @return array<string, float>
     */
    public function getOperationalExpenseMonthlySeries(int $userId, array $linkedAccountNumbers = []): array
    {
        $since = Carbon::now()->subMonths(24)->startOfMonth();
        $query = TransactionHistory::where('user_id', $userId)
            ->where('type', 'OUT')
            ->where('transaction_date', '>=', $since);
        if (! empty($linkedAccountNumbers)) {
            $query->whereIn('account_number', $linkedAccountNumbers);
        }
        $txs = $query->with('systemCategory')
            ->select('id', 'amount', 'transaction_date', 'description', 'system_category_id', 'classification_status', 'classification_confidence')
            ->get();

        if ($txs->isEmpty()) {
            return [];
        }

        $rawByMonth = $this->rawMonthlyTotals($txs);
        $medianMonthly = $this->median(array_values($rawByMonth));

        $byMonth = [];
        foreach ($txs as $t) {
            $score = $this->expenseScore($t, $medianMonthly);
            if ($score <= 0) {
                continue;
            }
            $key = Carbon::parse($t->transaction_date)->format('Y-m');
            if (! isset($byMonth[$key])) {
                $byMonth[$key] = 0.0;
            }
            $byMonth[$key] += abs((float) $t->amount) * $score;
        }

        ksort($byMonth);

        return $byMonth;
    }

    private function rawMonthlyTotals($txs): array
    {
        $out = [];
        foreach ($txs as $t) {
            $key = Carbon::parse($t->transaction_date)->format('Y-m');
            if (! isset($out[$key])) {
                $out[$key] = 0.0;
            }
            $out[$key] += abs((float) $t->amount);
        }

        return $out;
    }

    private function median(array $values): float
    {
        if (empty($values)) {
            return 0.0;
        }
        $copy = $values;
        sort($copy);
        $n = count($copy);
        $mid = (int) floor($n / 2);

        return $n % 2 === 0
            ? ($copy[$mid - 1] + $copy[$mid]) / 2
            : (float) $copy[$mid];
    }

    private function expenseScore(TransactionHistory $t, float $medianMonthlyExpense): float
    {
        $roleConf = $this->roleClassifier->classify($t, $medianMonthlyExpense);
        $role = $roleConf['role'] ?? FinancialRoleClassifier::UNKNOWN;
        $confidence = (float) ($roleConf['confidence'] ?? 0);

        if ($role === FinancialRoleClassifier::OPERATING_EXPENSE) {
            $amount = abs((float) $t->amount);
            $cfg = config('financial_roles.behavior_inference', []);
            $spikeMult = (float) ($cfg['spike_median_multiplier'] ?? 5);
            if ($medianMonthlyExpense > 0 && $amount > $spikeMult * $medianMonthlyExpense) {
                return min($confidence, 0.2);
            }
            return $confidence;
        }

        return 0.0;
    }
}
