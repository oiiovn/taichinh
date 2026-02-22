<?php

namespace App\Services;

use App\Models\TransactionHistory;
use App\Models\UserRecurringPattern;
use Carbon\Carbon;

/**
 * Adaptive Income Intelligence Engine 2.0.
 * Pipeline: Raw IN → Income Quality Filter (IncomePurityService) → Adjusted Monthly Income → EWMA Momentum → Reliability Blend → Shock Detection → Projected Income.
 *
 * - Income Purity: momentum chạy trên operational_income (đã loại vay/thu hồi nợ/one-off), confidence score theo category.
 * - EWMA: α theo volatility.
 * - Stability index: 1 - (std/mean) → giảm reliability_weight khi bất ổn.
 * - Shock detection, reliability blend, percentile cho risk.
 */
class AdaptiveIncomeEngine
{
    public function __construct(
        protected IncomePurityService $incomePurity
    ) {}

    /**
     * Thu nhập hiệu dụng/tháng — nguồn chuẩn duy nhất. Root cause, risk, projection, insight chỉ dùng giá trị này cho "thu".
     *
     * @param  array<string>  $linkedAccountNumbers
     */
    public function getEffectiveMonthlyIncome(int $userId, array $linkedAccountNumbers = []): float
    {
        $result = $this->estimate($userId, $linkedAccountNumbers);

        return (float) ($result['projected_income_per_month'] ?? 0);
    }

    /**
     * Ước tính thu nhập/tháng: EWMA + reliability blend, shock mode, percentile.
     *
     * @param  array<string>  $linkedAccountNumbers  Chỉ dùng giao dịch thuộc tài khoản đang liên kết. Rỗng = toàn bộ (backward compat).
     * @return array{
     *   projected_income_per_month: float,
     *   recurring_estimate: float,
     *   momentum_estimate: float,
     *   reliability_weight: float,
     *   shock_mode: bool,
     *   volatility_ratio: float,
     *   alpha_used: float,
     *   window_months: int,
     *   income_coverage_p25: float|null,
     *   income_coverage_p50: float|null,
     *   sources: array
     * }
     */
    public function estimate(int $userId, array $linkedAccountNumbers = []): array
    {
        $recurringEstimate = $this->recurringIncomePerMonth($userId);
        $byMonth = $this->operationalIncomeMonthlySeries($userId, $linkedAccountNumbers);

        $cfg = config('adaptive_income', []);
        $baseWindow = (int) ($cfg['window']['base_months'] ?? 12);
        $minWindow = (int) ($cfg['window']['min_months'] ?? 3);
        $maxWindow = (int) ($cfg['window']['max_months'] ?? 24);
        $shockRatio = (float) ($cfg['shock']['threshold_ratio'] ?? 0.4);

        $sorted = $this->sortedMonthlyTotals($byMonth);
        $n = count($sorted);
        if ($n === 0) {
            return $this->fallbackNoTransactionHistory($recurringEstimate);
        }

        $medianIncome = $this->incomeMedian($sorted);
        $volatilityRatio = $this->volatilityRatioFromSorted($sorted);
        $spikeMultiplier = $this->adaptiveSpikeMultiplier($volatilityRatio);
        $sorted = $this->incomeCapSpikeMonths($sorted, $medianIncome, $spikeMultiplier);

        $avgIncome = array_sum($sorted) / $n;
        $volatility = $this->stdDev($sorted);
        $volatilityRatio = $avgIncome > 0 ? min(1.0, $volatility / $avgIncome) : 0.0;

        $windowMonths = $this->dynamicWindow($baseWindow, $minWindow, $maxWindow, $volatilityRatio);
        $windowed = array_slice($sorted, -min($windowMonths, $n));
        $alpha = $this->alphaFromVolatility($volatilityRatio);

        $momentumEstimate = $this->ewma($windowed, $alpha);
        $currentMonthIncome = $this->currentMonthIncome($byMonth);
        $rollingEstimate = $momentumEstimate > 0 ? $momentumEstimate : $recurringEstimate;

        $stabilityIndex = $this->incomeStabilityIndex($sorted, $avgIncome);
        $shockMode = $rollingEstimate > 0 && $currentMonthIncome < $shockRatio * $rollingEstimate;
        $incomeForProjection = $shockMode
            ? $currentMonthIncome
            : $this->reliabilityBlend($recurringEstimate, $momentumEstimate, $volatilityRatio, $byMonth, $stabilityIndex);

        if ($incomeForProjection <= 0 && $recurringEstimate > 0) {
            $incomeForProjection = $recurringEstimate;
        }
        if ($incomeForProjection <= 0) {
            $incomeForProjection = $momentumEstimate > 0 ? $momentumEstimate : $avgIncome;
        }

        $coverageHistory = $this->incomeCoverageHistory($userId, $byMonth, $linkedAccountNumbers);
        $p25 = $this->percentile($coverageHistory, 25);
        $p50 = $this->percentile($coverageHistory, 50);

        $reliabilityWeight = $this->reliabilityWeight($recurringEstimate, $volatilityRatio, $byMonth, $stabilityIndex);

        $totalFromTx = array_sum($sorted);
        $recurringRatio = $totalFromTx > 0 ? min(1.0, $recurringEstimate * $n / $totalFromTx) : 0.0;
        $monthConsistency = $this->monthConsistency($sorted, $medianIncome);
        $volatilityAdjusted = max(0, 1 - $volatilityRatio);
        $stabilityScore = $this->incomeStabilityScore($volatilityAdjusted, $recurringRatio, $monthConsistency);

        $incomeP25 = $this->percentile($sorted, 25);
        $incomeP50 = $this->percentile($sorted, 50);
        $incomeP75 = $this->percentile($sorted, 75);
        $projectionMode = $stabilityScore < 0.4 ? 'probabilistic' : 'deterministic';

        $confidenceRange = $this->confidenceRange($incomeForProjection, $volatility, $stabilityScore);
        $incomeDriftSlopePct = $this->driftSlopePct($sorted);

        return [
            'projected_income_per_month' => (float) $incomeForProjection,
            'recurring_estimate' => (float) $recurringEstimate,
            'momentum_estimate' => (float) $momentumEstimate,
            'reliability_weight' => (float) $reliabilityWeight,
            'shock_mode' => $shockMode,
            'volatility_ratio' => (float) $volatilityRatio,
            'income_stability_index' => (float) $stabilityIndex,
            'income_stability_score' => (float) $stabilityScore,
            'confidence_range_low' => (float) $confidenceRange['low'],
            'confidence_range_high' => (float) $confidenceRange['high'],
            'confidence_pct' => (float) $confidenceRange['pct'],
            'income_drift_slope_pct' => $incomeDriftSlopePct,
            'spike_multiplier_used' => (float) $spikeMultiplier,
            'alpha_used' => (float) $alpha,
            'window_months' => $windowMonths,
            'income_coverage_p25' => $p25,
            'income_coverage_p50' => $p50,
            'projection_mode' => $projectionMode,
            'income_scenario_p25' => $incomeP25,
            'income_scenario_p50' => $incomeP50,
            'income_scenario_p75' => $incomeP75,
            'sources' => [
                'recurring_income' => round($recurringEstimate),
                'momentum_income' => round($momentumEstimate),
                'current_month_income' => round($currentMonthIncome),
                'months_with_data' => $n,
                'income_stability_score' => round($stabilityScore, 3),
                'confidence_range_low' => round($confidenceRange['low']),
                'confidence_range_high' => round($confidenceRange['high']),
                'confidence_pct' => round($confidenceRange['pct'], 1),
                'income_drift_slope_pct' => $incomeDriftSlopePct,
                'spike_multiplier_used' => round($spikeMultiplier, 2),
                'projection_mode' => $projectionMode,
                'income_scenario_p25' => $incomeP25 !== null ? round($incomeP25) : null,
                'income_scenario_p50' => $incomeP50 !== null ? round($incomeP50) : null,
                'income_scenario_p75' => $incomeP75 !== null ? round($incomeP75) : null,
            ],
        ];
    }

    private function volatilityRatioFromSorted(array $sorted): float
    {
        $n = count($sorted);
        if ($n < 2) {
            return 0.0;
        }
        $avg = array_sum($sorted) / $n;

        return $avg > 0 ? min(1.0, $this->stdDev($sorted) / $avg) : 0.0;
    }

    private function adaptiveSpikeMultiplier(float $volatilityRatio): float
    {
        $cfg = config('financial_structure.adaptive_threshold', []);
        $base = (float) ($cfg['spike_multiplier_base'] ?? 2.5);
        $volFactor = (float) ($cfg['spike_multiplier_volatility_factor'] ?? 0.5);

        return (float) max(1.5, min(5.0, $base * (1 + $volFactor * $volatilityRatio)));
    }

    private function monthConsistency(array $sortedValues, float $median): float
    {
        if (empty($sortedValues) || $median <= 0) {
            return 1.0;
        }
        $cfg = config('financial_structure.stability', []);
        $tolerance = (float) ($cfg['month_consistency_tolerance_pct'] ?? 0.20);
        $low = $median * (1 - $tolerance);
        $high = $median * (1 + $tolerance);
        $within = 0;
        foreach ($sortedValues as $v) {
            if ($v >= $low && $v <= $high) {
                $within++;
            }
        }

        return (float) ($within / count($sortedValues));
    }

    private function incomeStabilityScore(float $volatilityAdjusted, float $recurringRatio, float $monthConsistency): float
    {
        $cfg = config('financial_structure.stability', []);
        $w1 = (float) ($cfg['weight_volatility_adjusted'] ?? 0.4);
        $w2 = (float) ($cfg['weight_recurring_ratio'] ?? 0.35);
        $w3 = (float) ($cfg['weight_month_consistency'] ?? 0.25);

        return (float) max(0, min(1, $w1 * $volatilityAdjusted + $w2 * $recurringRatio + $w3 * $monthConsistency));
    }

    private function confidenceRange(float $projected, float $std, float $stabilityScore): array
    {
        $cfg = config('financial_structure.confidence', []);
        $k = (float) ($cfg['std_dev_multiplier'] ?? 1.5);
        $low = max(0, $projected - $k * $std);
        $high = $projected + $k * $std;
        $pct = (float) max(0, min(100, $stabilityScore * 100));

        return ['low' => $low, 'high' => $high, 'pct' => $pct];
    }

    /** Linear regression slope % (theo tháng): slope / mean * 100. Chronological order. */
    private function driftSlopePct(array $chronologicalValues): ?float
    {
        $n = count($chronologicalValues);
        if ($n < 3) {
            return null;
        }
        $mean = array_sum($chronologicalValues) / $n;
        if ($mean <= 0) {
            return null;
        }
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;
        for ($i = 0; $i < $n; $i++) {
            $x = $i;
            $y = (float) $chronologicalValues[$i];
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }
        $denom = $n * $sumX2 - $sumX * $sumX;
        if (abs($denom) < 1e-10) {
            return null;
        }
        $slope = ($n * $sumXY - $sumX * $sumY) / $denom;

        return (float) ($slope / $mean * 100);
    }

    private function incomeMedian(array $values): float
    {
        if (empty($values)) {
            return 0.0;
        }
        $copy = $values;
        sort($copy);
        $n = count($copy);
        $mid = (int) floor($n / 2);
        return $n % 2 === 0 ? ($copy[$mid - 1] + $copy[$mid]) / 2 : (float) $copy[$mid];
    }

    /** Income spike detection: tháng > multiplier * median → cap (không đưa full vào baseline). */
    private function incomeCapSpikeMonths(array $sorted, float $median, float $multiplier = 2.5): array
    {
        if ($median <= 0) {
            return $sorted;
        }
        $cap = $multiplier * $median;
        return array_map(fn ($v) => $v > $cap ? $cap : $v, $sorted);
    }

    /**
     * Chuỗi thu theo tháng: ưu tiên operational (đã lọc + confidence), fallback raw IN khi rỗng.
     *
     * @param  array<string>  $linkedAccountNumbers
     */
    private function operationalIncomeMonthlySeries(int $userId, array $linkedAccountNumbers = []): array
    {
        $operational = $this->incomePurity->getOperationalIncomeMonthlySeries($userId, $linkedAccountNumbers);
        if ($operational !== []) {
            return $operational;
        }

        return $this->monthlyIncomeFromTransactions($userId, $linkedAccountNumbers);
    }

    /**
     * income_stability_index = 1 - min(1, std/mean). Thấp → giảm reliability_weight.
     */
    private function incomeStabilityIndex(array $sortedValues, float $avgIncome): float
    {
        $cfg = config('income_purity.stability', []);
        $minMean = (float) ($cfg['min_mean_for_index'] ?? 100_000);
        if ($avgIncome < $minMean || empty($sortedValues)) {
            return 1.0;
        }
        $std = $this->stdDev($sortedValues);
        $ratio = $std / $avgIncome;

        return (float) max(0, min(1, 1 - $ratio));
    }

    private function recurringIncomePerMonth(int $userId): float
    {
        return (float) UserRecurringPattern::where('user_id', $userId)
            ->where('status', UserRecurringPattern::STATUS_ACTIVE)
            ->where('direction', 'IN')
            ->get()
            ->sum(fn ($p) => $this->patternMonthlyAmount($p));
    }

    private function patternMonthlyAmount(UserRecurringPattern $p): float
    {
        $avg = (float) $p->avg_amount;
        $interval = (float) ($p->avg_interval_days ?: 30);

        return $avg * (30 / $interval);
    }

    /**
     * @param  array<string>  $linkedAccountNumbers
     */
    private function monthlyIncomeFromTransactions(int $userId, array $linkedAccountNumbers = []): array
    {
        $since = Carbon::now()->subMonths(24)->startOfMonth();
        $query = TransactionHistory::where('user_id', $userId)
            ->where('type', 'IN')
            ->where('transaction_date', '>=', $since);
        if (! empty($linkedAccountNumbers)) {
            $query->whereIn('account_number', $linkedAccountNumbers);
        }
        $txs = $query->select('amount', 'transaction_date')
            ->get();
        $byMonth = [];
        foreach ($txs as $t) {
            $key = Carbon::parse($t->transaction_date)->format('Y-m');
            if (! isset($byMonth[$key])) {
                $byMonth[$key] = 0;
            }
            $byMonth[$key] += (float) $t->amount;
        }

        return $byMonth;
    }

    private function sortedMonthlyTotals(array $byMonth): array
    {
        ksort($byMonth);

        return array_values($byMonth);
    }

    private function stdDev(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0.0;
        }
        $avg = array_sum($values) / $n;
        $sumSq = 0.0;
        foreach ($values as $v) {
            $sumSq += ($v - $avg) ** 2;
        }

        return (float) sqrt($sumSq / ($n - 1));
    }

    private function alphaFromVolatility(float $volatilityRatio): float
    {
        $cfg = config('adaptive_income.ewma', []);
        $alphaMin = (float) ($cfg['alpha_min'] ?? 0.15);
        $alphaMax = (float) ($cfg['alpha_max'] ?? 0.6);
        $high = (float) ($cfg['volatility_high_ratio'] ?? 0.35);
        $low = (float) ($cfg['volatility_low_ratio'] ?? 0.08);
        if ($volatilityRatio <= $low) {
            return $alphaMin;
        }
        if ($volatilityRatio >= $high) {
            return $alphaMax;
        }
        $t = ($volatilityRatio - $low) / max(0.001, $high - $low);

        return $alphaMin + $t * ($alphaMax - $alphaMin);
    }

    private function ewma(array $sortedValues, float $alpha): float
    {
        if (empty($sortedValues)) {
            return 0.0;
        }
        $prev = (float) $sortedValues[0];
        for ($i = 1; $i < count($sortedValues); $i++) {
            $prev = $alpha * (float) $sortedValues[$i] + (1 - $alpha) * $prev;
        }

        return $prev;
    }

    private function dynamicWindow(int $base, int $min, int $max, float $volatilityRatio): int
    {
        $factor = (int) round($volatilityRatio * 6);
        $window = $base - $factor;

        return max($min, min($max, $window));
    }

    private function currentMonthIncome(array $byMonth): float
    {
        $key = Carbon::now()->format('Y-m');

        return (float) ($byMonth[$key] ?? 0);
    }

    /** recurring_ratio * (1 - volatility_ratio) * stability_index → khi bất ổn thì giảm weight. */
    private function reliabilityWeight(float $recurringEstimate, float $volatilityRatio, array $byMonth, float $stabilityIndex = 1.0): float
    {
        $totalFromTx = array_sum($byMonth);
        $nMonths = count($byMonth);
        if ($nMonths <= 0 || $totalFromTx <= 0) {
            return $recurringEstimate > 0 ? 1.0 : 0.0;
        }
        $avgTx = $totalFromTx / $nMonths;
        $recurringRatio = $avgTx > 0 ? min(1.0, $recurringEstimate / $avgTx) : 0.0;
        $incomeStability = 1 - $volatilityRatio;
        $w = $recurringRatio * $incomeStability * max(0, min(1, $stabilityIndex));

        return (float) max(0, min(1, $w));
    }

    private function reliabilityBlend(float $recurring, float $momentum, float $volatilityRatio, array $byMonth, float $stabilityIndex = 1.0): float
    {
        $w = $this->reliabilityWeight($recurring, $volatilityRatio, $byMonth, $stabilityIndex);

        return $w * $recurring + (1 - $w) * $momentum;
    }

    /**
     * @param  array<string>  $linkedAccountNumbers
     */
    private function incomeCoverageHistory(int $userId, array $incomeByMonth, array $linkedAccountNumbers = []): array
    {
        $since = Carbon::now()->subMonths(24)->startOfMonth();
        $query = TransactionHistory::where('user_id', $userId)
            ->where('type', 'OUT')
            ->where('transaction_date', '>=', $since);
        if (! empty($linkedAccountNumbers)) {
            $query->whereIn('account_number', $linkedAccountNumbers);
        }
        $out = $query->select('amount', 'transaction_date')
            ->get();
        $expenseByMonth = [];
        foreach ($out as $t) {
            $key = Carbon::parse($t->transaction_date)->format('Y-m');
            if (! isset($expenseByMonth[$key])) {
                $expenseByMonth[$key] = 0;
            }
            $expenseByMonth[$key] += abs((float) $t->amount);
        }
        $months = array_unique(array_merge(array_keys($incomeByMonth), array_keys($expenseByMonth)));
        sort($months);
        $coverages = [];
        foreach ($months as $m) {
            $inc = (float) ($incomeByMonth[$m] ?? 0);
            $exp = (float) ($expenseByMonth[$m] ?? 0);
            if ($exp > 0) {
                $coverages[] = $inc / $exp;
            }
        }

        return $coverages;
    }

    private function percentile(array $values, float $p): ?float
    {
        if (empty($values)) {
            return null;
        }
        $copy = $values;
        sort($copy);
        $n = count($copy);
        $idx = $p / 100 * ($n - 1);
        $lo = (int) floor($idx);
        $hi = (int) ceil($idx);
        if ($lo === $hi) {
            return (float) $copy[$lo];
        }
        $w = $idx - $lo;

        return (float) ($copy[$lo] * (1 - $w) + $copy[$hi] * $w);
    }

    private function fallbackNoTransactionHistory(float $recurringEstimate): array
    {
        $cfg = config('adaptive_income.risk_percentile', []);

        return [
            'projected_income_per_month' => (float) $recurringEstimate,
            'recurring_estimate' => (float) $recurringEstimate,
            'momentum_estimate' => 0.0,
            'reliability_weight' => 1.0,
            'shock_mode' => false,
            'volatility_ratio' => 0.0,
            'income_stability_index' => 1.0,
            'income_stability_score' => 1.0,
            'confidence_range_low' => (float) $recurringEstimate,
            'confidence_range_high' => (float) $recurringEstimate,
            'confidence_pct' => 100.0,
            'income_drift_slope_pct' => null,
            'spike_multiplier_used' => (float) (config('financial_structure.adaptive_threshold.spike_multiplier_base', 2.5)),
            'alpha_used' => (float) (config('adaptive_income.ewma.alpha_min', 0.15)),
            'window_months' => (int) (config('adaptive_income.window.base_months', 12)),
            'income_coverage_p25' => (float) ($cfg['coverage_p25_fallback'] ?? 0.15),
            'income_coverage_p50' => (float) ($cfg['coverage_p50_fallback'] ?? 0.35),
            'projection_mode' => 'deterministic',
            'income_scenario_p25' => (float) $recurringEstimate,
            'income_scenario_p50' => (float) $recurringEstimate,
            'income_scenario_p75' => (float) $recurringEstimate,
            'sources' => [
                'recurring_income' => round($recurringEstimate),
                'momentum_income' => 0,
                'current_month_income' => 0,
                'months_with_data' => 0,
                'income_stability_score' => 1.0,
                'confidence_range_low' => round($recurringEstimate),
                'confidence_range_high' => round($recurringEstimate),
                'confidence_pct' => 100.0,
                'income_drift_slope_pct' => null,
                'spike_multiplier_used' => 2.5,
                'projection_mode' => 'deterministic',
                'income_scenario_p25' => round($recurringEstimate),
                'income_scenario_p50' => round($recurringEstimate),
                'income_scenario_p75' => round($recurringEstimate),
            ],
        ];
    }
}
