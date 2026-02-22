<?php

namespace App\Services;

use App\Models\TransactionHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AnalyticsAggregateService
{
    /** Ngưỡng % tăng chi so với TB để cảnh báo bất thường */
    private const ANOMALY_EXPENSE_SPIKE_PCT = 35;

    /** Ngưỡng tỷ trọng 1 danh mục để cảnh báo tập trung */
    private const ANOMALY_TOP_CATEGORY_PCT = 75;

    public function __construct(
        protected TrajectoryAnalyzerService $trajectoryAnalyzer
    ) {}

    /**
     * Thu/chi theo tháng (chronological), surplus, trajectory, net, burn, stability, trend %, anomaly.
     *
     * @param  array<string>  $linkedAccountNumbers
     */
    public function monthlyInOut(int $userId, array $linkedAccountNumbers = [], int $months = 12): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths($months - 1);
        $end = Carbon::now()->endOfMonth();

        $query = TransactionHistory::query()
            ->where('user_id', $userId)
            ->whereBetween('transaction_date', [$start, $end]);

        if (! empty($linkedAccountNumbers)) {
            $query->where(function ($q) use ($linkedAccountNumbers) {
                $q->whereIn('account_number', $linkedAccountNumbers)
                    ->orWhereHas('bankAccount', fn ($q2) => $q2->whereIn('account_number', $linkedAccountNumbers));
            });
        }

        $rows = $query->selectRaw("
            DATE_FORMAT(transaction_date, '%Y-%m') as month_key,
            SUM(CASE WHEN type = 'IN' THEN amount ELSE 0 END) as thu,
            SUM(CASE WHEN type = 'OUT' THEN ABS(amount) ELSE 0 END) as chi
        ")->groupBy('month_key')->orderBy('month_key')->get();

        $byMonth = [];
        $surplusSeries = [];
        $totalThu = 0.0;
        $totalChi = 0.0;

        for ($i = 0; $i < $months; $i++) {
            $d = $start->copy()->addMonths($i);
            $key = $d->format('Y-m');
            $label = $this->monthLabel($d);
            $thu = 0.0;
            $chi = 0.0;
            foreach ($rows as $r) {
                if ($r->month_key === $key) {
                    $thu = (float) $r->thu;
                    $chi = (float) $r->chi;
                    break;
                }
            }
            $surplus = $thu - $chi;
            $byMonth[] = [
                'month_key' => $key,
                'month_label' => $label,
                'thu' => $thu,
                'chi' => $chi,
                'surplus' => $surplus,
            ];
            $surplusSeries[] = $surplus;
            $totalThu += $thu;
            $totalChi += $chi;
        }

        $trajectory = $this->trajectoryAnalyzer->analyze($surplusSeries);
        $countMonths = count($byMonth) ?: 1;
        $avgThu = $totalThu / $countMonths;
        $avgChi = $totalChi / $countMonths;

        $netCashflow = $totalThu - $totalChi;
        $netPrevPeriod = null;
        $pctChangeNet = null;
        if ($months >= 2) {
            $prevNet = $this->netCashflowPrevPeriod($userId, $linkedAccountNumbers, $months);
            $netPrevPeriod = $prevNet['net'];
            if ($netPrevPeriod !== null && abs($netPrevPeriod) >= 1) {
                $pctChangeNet = (($netCashflow - $netPrevPeriod) / abs($netPrevPeriod)) * 100;
            }
        }

        $burnRatio = $totalThu > 0 ? ($totalChi / $totalThu) * 100 : null;

        $stability = $this->computeStability($byMonth);
        $trendPctPerMonth = $trajectory['slope_normalized'] !== null ? round($trajectory['slope_normalized'] * 100, 1) : null;
        $trajectory['trend_pct_per_month'] = $trendPctPerMonth;
        $trajectory['trend_label'] = $this->trendLabel($trajectory['direction'], $trendPctPerMonth);

        $anomalyAlerts = $this->detectAnomalies($byMonth, $avgChi);

        return [
            'monthly' => $byMonth,
            'trajectory' => $trajectory,
            'summary' => [
                'total_thu' => $totalThu,
                'total_chi' => $totalChi,
                'avg_thu' => $avgThu,
                'avg_chi' => $avgChi,
                'net_cashflow' => $netCashflow,
                'net_prev_period' => $netPrevPeriod,
                'pct_change_net' => $pctChangeNet,
                'burn_ratio' => $burnRatio,
            ],
            'stability' => $stability,
            'anomaly_alerts' => $anomalyAlerts,
        ];
    }

    private function netCashflowPrevPeriod(int $userId, array $linkedAccountNumbers, int $months): array
    {
        $endPrev = Carbon::now()->startOfMonth()->subMonth()->endOfMonth();
        $startPrev = Carbon::now()->startOfMonth()->subMonths($months);

        $query = TransactionHistory::query()
            ->where('user_id', $userId)
            ->whereBetween('transaction_date', [$startPrev, $endPrev]);
        if (! empty($linkedAccountNumbers)) {
            $query->where(function ($q) use ($linkedAccountNumbers) {
                $q->whereIn('account_number', $linkedAccountNumbers)
                    ->orWhereHas('bankAccount', fn ($q2) => $q2->whereIn('account_number', $linkedAccountNumbers));
            });
        }
        $row = $query->selectRaw("
            SUM(CASE WHEN type = 'IN' THEN amount ELSE 0 END) - SUM(CASE WHEN type = 'OUT' THEN ABS(amount) ELSE 0 END) as net
        ")->first();
        $net = $row && $row->net !== null ? (float) $row->net : null;
        return ['net' => $net];
    }

    private function computeStability(array $byMonth): array
    {
        $n = count($byMonth);
        if ($n < 2) {
            return ['score' => null, 'label' => null, 'cv_thu' => null, 'cv_chi' => null];
        }
        $thuArr = array_column($byMonth, 'thu');
        $chiArr = array_column($byMonth, 'chi');
        $meanThu = array_sum($thuArr) / $n;
        $meanChi = array_sum($chiArr) / $n;
        $stdThu = $this->stdDev($thuArr);
        $stdChi = $this->stdDev($chiArr);
        $cvThu = $meanThu > 0 ? ($stdThu / $meanThu) * 100 : 0;
        $cvChi = $meanChi > 0 ? ($stdChi / $meanChi) * 100 : 0;
        $score = (int) round(max(0, 100 - ($cvThu + $cvChi) / 2));
        $label = $score >= 70 ? 'Ổn định cao' : ($score >= 40 ? 'Biến động vừa' : 'Không ổn định');
        return [
            'score' => $score,
            'label' => $label,
            'cv_thu' => round($cvThu, 1),
            'cv_chi' => round($cvChi, 1),
        ];
    }

    private function stdDev(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0.0;
        }
        $mean = array_sum($values) / $n;
        $sumSq = 0.0;
        foreach ($values as $v) {
            $sumSq += ($v - $mean) ** 2;
        }
        return (float) sqrt($sumSq / ($n - 1));
    }

    private function trendLabel(string $direction, ?float $pctPerMonth): string
    {
        if ($pctPerMonth === null) {
            return $direction === 'improving' ? 'Đang cải thiện' : ($direction === 'deteriorating' ? 'Đang giảm' : 'Ổn định');
        }
        $abs = abs($pctPerMonth);
        if ($direction === 'improving') {
            return sprintf('Tăng +%s%%/tháng trong kỳ', number_format($abs, 1));
        }
        if ($direction === 'deteriorating') {
            return sprintf('Giảm -%s%%/tháng trong kỳ', number_format($abs, 1));
        }
        return 'Dòng tiền ổn định';
    }

    private function detectAnomalies(array $byMonth, float $avgChi): array
    {
        $alerts = [];
        if (count($byMonth) < 2 || $avgChi <= 0) {
            return $alerts;
        }
        $last = end($byMonth);
        $lastChi = (float) ($last['chi'] ?? 0);
        $pctVsAvg = $avgChi > 0 ? (($lastChi - $avgChi) / $avgChi) * 100 : 0;
        if ($pctVsAvg >= self::ANOMALY_EXPENSE_SPIKE_PCT) {
            $alerts[] = [
                'type' => 'expense_spike',
                'message' => sprintf('Tháng gần nhất chi tăng %s%% so với trung bình %d tháng.', number_format($pctVsAvg, 0), count($byMonth)),
            ];
        }
        return $alerts;
    }

    /**
     * Chi tiêu theo danh mục (OUT), ưu tiên user_category.
     *
     * @param  array<string>  $linkedAccountNumbers
     * @return array<int, array{name: string, total: float, count: int, pct: float}>
     */
    public function expenseByCategory(int $userId, array $linkedAccountNumbers = [], int $months = 12): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths($months - 1);
        $end = Carbon::now()->endOfMonth();

        $query = TransactionHistory::query()
            ->where('user_id', $userId)
            ->where('type', 'OUT')
            ->whereBetween('transaction_date', [$start, $end]);

        if (! empty($linkedAccountNumbers)) {
            $query->where(function ($q) use ($linkedAccountNumbers) {
                $q->whereIn('account_number', $linkedAccountNumbers)
                    ->orWhereHas('bankAccount', fn ($q2) => $q2->whereIn('account_number', $linkedAccountNumbers));
            });
        }

        $rows = $query->selectRaw('
            user_category_id,
            system_category_id,
            SUM(ABS(amount)) as total,
            COUNT(*) as cnt
        ')->groupBy('user_category_id', 'system_category_id')->get();

        $categoryNames = $this->resolveCategoryNamesFromRows($rows);
        $totalOut = $rows->sum('total');
        $totalOut = $totalOut > 0 ? (float) $totalOut : 1.0;

        $byName = [];
        foreach ($rows as $r) {
            $id = $r->user_category_id ?? $r->system_category_id;
            $name = $id ? ($categoryNames[$id] ?? 'Danh mục #' . $id) : 'Chưa phân loại';
            $total = (float) $r->total;
            $count = (int) $r->cnt;
            if (! isset($byName[$name])) {
                $byName[$name] = ['name' => $name, 'total' => 0, 'count' => 0];
            }
            $byName[$name]['total'] += $total;
            $byName[$name]['count'] += $count;
        }
        $totalOut = array_sum(array_column($byName, 'total'));
        $totalOut = $totalOut > 0 ? (float) $totalOut : 1.0;
        $out = [];
        foreach ($byName as $name => $item) {
            $out[] = [
                'name' => $name,
                'total' => $item['total'],
                'count' => $item['count'],
                'pct' => round($item['total'] / $totalOut * 100, 1),
            ];
        }
        usort($out, fn ($a, $b) => $b['total'] <=> $a['total']);
        $out = array_values($out);

        $top1Pct = $out[0]['pct'] ?? 0;
        $hhi = 0.0;
        foreach ($out as $r) {
            $share = $r['pct'] / 100.0;
            $hhi += $share * $share;
        }
        $hhiPct = (int) round($hhi * 100);
        $concentrationLabel = $top1Pct >= 75 ? 'Rủi ro tập trung cao' : ($top1Pct >= 50 ? 'Tập trung vừa' : 'Phân tán an toàn');

        return [
            'items' => $out,
            'concentration' => [
                'top1_pct' => $top1Pct,
                'hhi' => $hhiPct,
                'label' => $concentrationLabel,
            ],
        ];
    }

    private function monthLabel(Carbon $d): string
    {
        $months = ['T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'T8', 'T9', 'T10', 'T11', 'T12'];
        return $months[(int) $d->format('n') - 1] . ' ' . $d->format('y');
    }

    private function resolveCategoryNamesFromRows(Collection $rows): array
    {
        $userIds = $rows->pluck('user_category_id')->filter()->unique()->values()->all();
        $systemIds = $rows->pluck('system_category_id')->filter()->unique()->values()->all();
        $names = [];
        if (! empty($userIds)) {
            $userCats = \App\Models\UserCategory::whereIn('id', $userIds)->get()->keyBy('id');
            foreach ($userIds as $id) {
                $c = $userCats->get($id);
                $names[$id] = $c ? $c->name : 'Danh mục #' . $id;
            }
        }
        if (! empty($systemIds)) {
            $systemCats = \App\Models\SystemCategory::whereIn('id', $systemIds)->get()->keyBy('id');
            foreach ($systemIds as $id) {
                $c = $systemCats->get($id);
                $names[$id] = $c ? $c->name : 'Danh mục #' . $id;
            }
        }
        return $names;
    }
}
