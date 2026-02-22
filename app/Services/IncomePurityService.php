<?php

namespace App\Services;

use App\Models\TransactionHistory;
use Carbon\Carbon;

/**
 * Income Quality Layer: chỉ OPERATING_INCOME (và ONE_OFF_INCOME với weight thấp).
 * Raw IN → FinancialRoleClassifier → role + confidence → adjusted_monthly_series.
 * Không dùng FINANCING_INFLOW làm thu. Pending qua behavior inference, dynamic confidence.
 */
class IncomePurityService
{
    public function __construct(
        protected FinancialRoleClassifier $roleClassifier
    ) {}

    /**
     * Chuỗi thu operating theo tháng: SUM(amount * score) với role = OPERATING_INCOME (ONE_OFF cap).
     * Chỉ giao dịch thuộc tài khoản đang liên kết khi linkedAccountNumbers không rỗng.
     *
     * @param  array<string>  $linkedAccountNumbers
     * @return array<string, float>
     */
    public function getOperationalIncomeMonthlySeries(int $userId, array $linkedAccountNumbers = []): array
    {
        $since = Carbon::now()->subMonths(24)->startOfMonth();
        $query = TransactionHistory::where('user_id', $userId)
            ->where('type', 'IN')
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
            $score = $this->incomeScore($t, $medianMonthly);
            if ($score <= 0) {
                continue;
            }
            $key = Carbon::parse($t->transaction_date)->format('Y-m');
            if (! isset($byMonth[$key])) {
                $byMonth[$key] = 0.0;
            }
            $byMonth[$key] += (float) $t->amount * $score;
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
            $out[$key] += (float) $t->amount;
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

    /**
     * Chỉ OPERATING_INCOME (và ONE_OFF với cap). Dynamic confidence từ role classifier.
     */
    private function incomeScore(TransactionHistory $t, float $medianMonthlyIncome): float
    {
        $roleConf = $this->roleClassifier->classify($t, $medianMonthlyIncome);
        $role = $roleConf['role'] ?? FinancialRoleClassifier::UNKNOWN;
        $confidence = (float) ($roleConf['confidence'] ?? 0);

        if ($role === FinancialRoleClassifier::OPERATING_INCOME) {
            $cfg = config('income_purity.one_off', []);
            $amount = abs((float) $t->amount);
            $mult = (float) ($cfg['median_multiplier'] ?? 3);
            $cap = (float) ($cfg['score_cap'] ?? 0.2);
            if ($medianMonthlyIncome > 0 && $amount > $mult * $medianMonthlyIncome) {
                return min($confidence, $cap);
            }
            return $confidence;
        }

        if ($role === FinancialRoleClassifier::ONE_OFF_INCOME) {
            $cfg = config('income_purity.one_off', []);
            return min($confidence, (float) ($cfg['score_cap'] ?? 0.2));
        }

        return 0.0;
    }
}
