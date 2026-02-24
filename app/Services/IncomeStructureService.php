<?php

namespace App\Services;

use App\Models\TransactionHistory;
use App\Models\UserIncomeSource;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Structural Layer: volatility, contribution ratio, diversification theo từng nguồn thu (user_income_sources).
 * Dùng giao dịch IN đã có income_source_id.
 */
class IncomeStructureService
{
    /**
     * Cấu trúc thu theo nguồn: % đóng góp, volatility từng nguồn, diversification index.
     *
     * @param  array<string>  $linkedAccountNumbers
     * @return array{
     *   sources: array<int, array{id: int, name: string, contribution_pct: float, total_vnd: float, volatility: float, months: int}>,
     *   total_vnd: float,
     *   diversification_index: float,
     *   concentration_risk: bool,
     *   unknown_pct: float
     * }
     */
    public function getStructure(int $userId, int $months = 24, array $linkedAccountNumbers = []): array
    {
        $since = Carbon::now()->subMonths($months)->startOfMonth();

        $query = TransactionHistory::query()
            ->where('user_id', $userId)
            ->where('type', 'IN')
            ->where('transaction_date', '>=', $since);
        if (! empty($linkedAccountNumbers)) {
            $query->where(function ($q) use ($linkedAccountNumbers) {
                $q->whereIn('account_number', $linkedAccountNumbers)
                    ->orWhereHas('bankAccount', fn ($q2) => $q2->whereIn('account_number', $linkedAccountNumbers));
            });
        }

        $totals = $query->select('income_source_id', DB::raw('SUM(ABS(amount)) as total'))
            ->groupBy('income_source_id')
            ->pluck('total', 'income_source_id')
            ->map(fn ($v) => (float) $v);

        $totalVnd = $totals->sum();
        $sources = [];
        $sourceIds = $totals->keys()->filter(fn ($k) => $k !== null)->values();
        $sourceModels = $sourceIds->isEmpty() ? collect() : UserIncomeSource::whereIn('id', $sourceIds)->get()->keyBy('id');

        foreach ($totals as $sourceId => $sum) {
            if ($sourceId === null) {
                continue;
            }
            $src = $sourceModels->get($sourceId);
            $pct = $totalVnd > 0 ? ($sum / $totalVnd) * 100 : 0.0;
            $volatility = $this->volatilityForSource($userId, (int) $sourceId, $since, $linkedAccountNumbers);
            $sources[(int) $sourceId] = [
                'id' => (int) $sourceId,
                'name' => $src ? $src->name : ('Nguồn #' . $sourceId),
                'contribution_pct' => round($pct, 2),
                'total_vnd' => $sum,
                'volatility' => round($volatility, 4),
                'months' => $this->countMonthsWithData($userId, (int) $sourceId, $since, $linkedAccountNumbers),
            ];
        }

        $unknownSum = (float) ($totals->get(null) ?? 0);
        $unknownPct = $totalVnd > 0 ? round(($unknownSum / $totalVnd) * 100, 2) : 0.0;

        $herfindahl = 0.0;
        foreach ($sources as $s) {
            $p = $s['contribution_pct'] / 100;
            $herfindahl += $p * $p;
        }
        $diversificationIndex = $herfindahl > 0 ? min(1.0, 1 - $herfindahl) : 0.0;
        $concentrationRisk = false;
        foreach ($sources as $s) {
            if ($s['contribution_pct'] >= 70) {
                $concentrationRisk = true;
                break;
            }
        }

        return [
            'sources' => $sources,
            'total_vnd' => $totalVnd,
            'diversification_index' => round($diversificationIndex, 4),
            'concentration_risk' => $concentrationRisk,
            'unknown_pct' => $unknownPct,
        ];
    }

    private function volatilityForSource(int $userId, int $incomeSourceId, Carbon $since, array $linkedAccountNumbers): float
    {
        $query = TransactionHistory::query()
            ->where('user_id', $userId)
            ->where('type', 'IN')
            ->where('income_source_id', $incomeSourceId)
            ->where('transaction_date', '>=', $since);
        if (! empty($linkedAccountNumbers)) {
            $query->where(function ($q) use ($linkedAccountNumbers) {
                $q->whereIn('account_number', $linkedAccountNumbers)
                    ->orWhereHas('bankAccount', fn ($q2) => $q2->whereIn('account_number', $linkedAccountNumbers));
            });
        }
        $byMonth = $query->selectRaw('DATE_FORMAT(transaction_date, "%Y-%m") as ym, SUM(ABS(amount)) as total')
            ->groupByRaw('DATE_FORMAT(transaction_date, "%Y-%m")')
            ->pluck('total')
            ->map(fn ($v) => (float) $v)
            ->values()
            ->all();

        if (count($byMonth) < 2) {
            return 0.0;
        }
        $avg = array_sum($byMonth) / count($byMonth);
        if ($avg <= 0) {
            return 0.0;
        }
        $variance = 0.0;
        foreach ($byMonth as $v) {
            $variance += ($v - $avg) ** 2;
        }
        $std = sqrt($variance / count($byMonth));

        return min(1.0, $std / $avg);
    }

    private function countMonthsWithData(int $userId, int $incomeSourceId, Carbon $since, array $linkedAccountNumbers): int
    {
        $query = TransactionHistory::query()
            ->where('user_id', $userId)
            ->where('type', 'IN')
            ->where('income_source_id', $incomeSourceId)
            ->where('transaction_date', '>=', $since);
        if (! empty($linkedAccountNumbers)) {
            $query->where(function ($q) use ($linkedAccountNumbers) {
                $q->whereIn('account_number', $linkedAccountNumbers)
                    ->orWhereHas('bankAccount', fn ($q2) => $q2->whereIn('account_number', $linkedAccountNumbers));
            });
        }

        return (int) ($query->selectRaw('COUNT(DISTINCT DATE_FORMAT(transaction_date, "%Y-%m")) as c')->value('c') ?? 0);
    }
}
