<?php

namespace App\Services;

use App\Models\TransactionHistory;
use App\Models\User;
use Carbon\Carbon;

/**
 * Adaptive Threshold Engine: tính metrics theo user (volatility, spend) rồi suy ngưỡng cảnh báo.
 * Người biến động cao → ngưỡng cao hơn (ít báo nhiễu). Người ổn định → ngưỡng nhạy hơn.
 */
class AdaptiveThresholdService
{
    /** Số ngày lấy giao dịch để tính metrics. */
    private const LOOKBACK_DAYS = 60;

    /** Recompute metrics nếu cũ hơn N ngày. */
    private const RECOMPUTE_STALE_DAYS = 1;

    /** Base ratio spend spike (150%). */
    private const BASE_SPIKE_RATIO = 1.5;

    /** Base week anomaly %. */
    private const BASE_WEEK_ANOMALY_PCT = 50;

    /** Sàn tuyệt đối balance change (5M VND). */
    private const BALANCE_CHANGE_ABSOLUTE_FLOOR = 5000000;

    /** Hệ số: balance_threshold = max(median_daily_spend × K, floor). */
    private const K_DAYS_FOR_BALANCE_THRESHOLD = 3;

    /** Số dư hiện tại × tỉ lệ này = phần tham gia ngưỡng balance_change (5%). */
    private const BALANCE_CHANGE_PCT_OF_CURRENT = 0.05;

    /** Số ngày có giao dịch tối thiểu mới tính volatility (tránh CV nhiễu). */
    private const MIN_DAYS_FOR_VOLATILITY = 14;

    /** Winsorize: cắt ở phân vị này (0.1 = bỏ 10% đầu/cuối) để CV ổn định. */
    private const WINSORIZE_LOWER_PERCENTILE = 0.10;

    private const WINSORIZE_UPPER_PERCENTILE = 0.90;

    /** Trọng số blend: income_stability = (1-vol)*w + regularity*(1-w). */
    private const INCOME_STABILITY_VOL_WEIGHT = 0.5;

    /**
     * Tính và lưu 5 metrics cho user; trả về mảng metrics.
     *
     * @return array{volatility_score_income: float, volatility_score_expense: float, avg_transaction_size: float, median_daily_spend: float, income_stability_index: float}
     */
    public function computeAndSaveMetrics(int $userId, array $linkedAccountNumbers = []): array
    {
        $metrics = $this->computeMetrics($userId, $linkedAccountNumbers);
        $user = User::find($userId);
        if ($user) {
            $user->volatility_score_income = $metrics['volatility_score_income'];
            $user->volatility_score_expense = $metrics['volatility_score_expense'];
            $user->avg_transaction_size = (int) round($metrics['avg_transaction_size']);
            $user->median_daily_spend = (int) round($metrics['median_daily_spend']);
            $user->income_stability_index = $metrics['income_stability_index'];
            $user->threshold_metrics_computed_at = now();
            $user->save();
        }
        return $metrics;
    }

    /**
     * Lấy ngưỡng adaptive. Nếu chưa có metrics hoặc đã cũ thì tính lại.
     * Ưu tiên: user override (từ request/model) > adaptive > constant.
     * spike_ratio và week_anomaly chỉ dùng volatility_expense (chi). balance_change có thể dùng totalBalance (5% số dư).
     *
     * @param  float|null  $totalBalance  Tổng số dư linked accounts (VND); nếu có thì balance_change = max(..., totalBalance × 5%, floor)
     * @return array{spike_ratio: float, balance_change_amount: int, week_anomaly_pct: int}
     */
    public function getAdaptiveThresholds(User $user, array $linkedAccountNumbers = [], ?float $totalBalance = null): array
    {
        $userId = $user->id;
        $computedAt = $user->threshold_metrics_computed_at;
        $stale = ! $computedAt || $computedAt->lt(Carbon::now()->subDays(self::RECOMPUTE_STALE_DAYS));
        $hasMetrics = $user->volatility_score_expense !== null && $user->median_daily_spend !== null;

        if ($stale || ! $hasMetrics) {
            $this->computeAndSaveMetrics($userId, $linkedAccountNumbers);
            $user->refresh();
        }

        $volatilityExpense = (float) ($user->volatility_score_expense ?? 0);
        $medianDailySpend = (float) ($user->median_daily_spend ?? 0);

        $expenseFactor = min(1.0, $volatilityExpense);
        $spikeRatio = round(self::BASE_SPIKE_RATIO * (1 + $expenseFactor), 2);
        $weekAnomalyPct = (int) round(self::BASE_WEEK_ANOMALY_PCT * (1 + $expenseFactor));

        $balanceFromSpend = $medianDailySpend * self::K_DAYS_FOR_BALANCE_THRESHOLD;
        $balanceFromPct = ($totalBalance !== null && $totalBalance > 0)
            ? (int) round($totalBalance * self::BALANCE_CHANGE_PCT_OF_CURRENT)
            : 0;
        $balanceChangeAmount = (int) max(
            $balanceFromSpend,
            $balanceFromPct,
            self::BALANCE_CHANGE_ABSOLUTE_FLOOR
        );

        return [
            'spike_ratio' => $spikeRatio,
            'balance_change_amount' => $balanceChangeAmount,
            'week_anomaly_pct' => $weekAnomalyPct,
        ];
    }

    /**
     * Tính 5 metrics từ giao dịch (không ghi DB).
     *
     * @return array{volatility_score_income: float, volatility_score_expense: float, avg_transaction_size: float, median_daily_spend: float, income_stability_index: float}
     */
    public function computeMetrics(int $userId, array $linkedAccountNumbers = []): array
    {
        $from = Carbon::now()->subDays(self::LOOKBACK_DAYS)->startOfDay();
        $to = Carbon::yesterday()->endOfDay();

        $query = TransactionHistory::where('user_id', $userId)
            ->whereBetween('transaction_date', [$from, $to]);

        if (! empty($linkedAccountNumbers)) {
            $query->whereIn('account_number', $linkedAccountNumbers);
        }

        $txs = $query->select('amount', 'type', 'transaction_date')->get();
        if ($txs->isEmpty()) {
            return $this->defaultMetrics();
        }

        $dailyIn = [];
        $dailyOut = [];
        $amounts = [];
        foreach ($txs as $t) {
            $date = Carbon::parse($t->transaction_date)->format('Y-m-d');
            $amt = (float) $t->amount;
            $amounts[] = abs($amt);
            if ($t->type === 'IN') {
                $dailyIn[$date] = ($dailyIn[$date] ?? 0) + $amt;
            } else {
                $dailyOut[$date] = ($dailyOut[$date] ?? 0) + $amt;
            }
        }

        $volatilityIncome = $this->volatilityScore(array_values($dailyIn));
        $volatilityExpense = $this->volatilityScore(array_values($dailyOut));
        $avgTransactionSize = $amounts ? array_sum($amounts) / count($amounts) : 0;
        $medianDailySpend = $this->median(array_values($dailyOut));
        $regularityScore = $this->incomeRegularityScore($dailyIn);
        $fromVol = 1.0 - min(1.0, $volatilityIncome);
        $incomeStabilityIndex = $regularityScore !== null
            ? self::INCOME_STABILITY_VOL_WEIGHT * $fromVol + (1 - self::INCOME_STABILITY_VOL_WEIGHT) * $regularityScore
            : $fromVol;

        return [
            'volatility_score_income' => round($volatilityIncome, 4),
            'volatility_score_expense' => round($volatilityExpense, 4),
            'avg_transaction_size' => round($avgTransactionSize, 0),
            'median_daily_spend' => round($medianDailySpend, 0),
            'income_stability_index' => round($incomeStabilityIndex, 4),
        ];
    }

    /**
     * Hệ số biến thiên (CV) chuẩn hóa 0..1 (cap 1).
     * Minimum sample guard: cần ít nhất MIN_DAYS_FOR_VOLATILITY ngày có giao dịch.
     * Winsorized CV: clamp giá trị ở phân vị 10% và 90% để giảm nhiễu khi mean nhỏ hoặc outlier.
     */
    private function volatilityScore(array $values): float
    {
        $values = array_values(array_filter($values, fn ($v) => (float) $v > 0));
        if (count($values) < self::MIN_DAYS_FOR_VOLATILITY) {
            return 0.0;
        }
        $winsorized = $this->winsorize($values, self::WINSORIZE_LOWER_PERCENTILE, self::WINSORIZE_UPPER_PERCENTILE);
        $mean = array_sum($winsorized) / count($winsorized);
        if ($mean <= 0) {
            return 0.0;
        }
        $variance = 0;
        foreach ($winsorized as $v) {
            $variance += (($v - $mean) ** 2);
        }
        $variance /= count($winsorized);
        $std = sqrt($variance);
        $cv = $std / $mean;
        return min(1.0, $cv);
    }

    /**
     * Winsorize: giá trị < p_lower thay bằng p_lower, giá trị > p_upper thay bằng p_upper.
     */
    private function winsorize(array $values, float $lowerPct, float $upperPct): array
    {
        if (empty($values)) {
            return $values;
        }
        $sorted = $values;
        sort($sorted);
        $n = count($sorted);
        $idxLo = (int) floor($n * $lowerPct);
        $idxHi = min($n - 1, (int) ceil($n * $upperPct));
        $lo = $sorted[$idxLo];
        $hi = $sorted[$idxHi];
        return array_map(fn ($v) => max($lo, min($hi, (float) $v)), $values);
    }

    /**
     * Điểm đều đặn thu nhập: từ các ngày có thu, tính khoảng cách (ngày) giữa các lần thu; CV thấp = đều = ổn định.
     * Trả về 0..1 (cao = ổn định). Null nếu không đủ dữ liệu (ít hơn 2 lần thu).
     */
    private function incomeRegularityScore(array $dailyIn): ?float
    {
        $datesWithIncome = array_keys(array_filter($dailyIn, fn ($v) => (float) $v > 0));
        sort($datesWithIncome);
        if (count($datesWithIncome) < 2) {
            return null;
        }
        $intervals = [];
        for ($i = 1; $i < count($datesWithIncome); $i++) {
            $d1 = Carbon::parse($datesWithIncome[$i - 1]);
            $d2 = Carbon::parse($datesWithIncome[$i]);
            $intervals[] = (float) $d1->diffInDays($d2);
        }
        if (empty($intervals)) {
            return null;
        }
        $mean = array_sum($intervals) / count($intervals);
        if ($mean <= 0) {
            return 1.0;
        }
        $variance = 0;
        foreach ($intervals as $x) {
            $variance += (($x - $mean) ** 2);
        }
        $variance /= count($intervals);
        $std = sqrt($variance);
        $cv = $std / $mean;
        return 1.0 - min(1.0, $cv);
    }

    private function median(array $values): float
    {
        $values = array_filter($values, fn ($v) => (float) $v >= 0);
        if (empty($values)) {
            return 0.0;
        }
        $values = array_values($values);
        sort($values);
        $n = count($values);
        $mid = (int) floor($n / 2);
        if ($n % 2 === 1) {
            return (float) $values[$mid];
        }
        return (float) (($values[$mid - 1] + $values[$mid]) / 2);
    }

    private function defaultMetrics(): array
    {
        return [
            'volatility_score_income' => 0.0,
            'volatility_score_expense' => 0.0,
            'avg_transaction_size' => 0.0,
            'median_daily_spend' => 0.0,
            'income_stability_index' => 1.0,
        ];
    }
}
