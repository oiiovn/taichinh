<?php

namespace App\Services;

use App\Models\BudgetThreshold;
use App\Models\BudgetThresholdEvent;
use App\Models\BudgetThresholdSnapshot;
use App\Models\TransactionHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Ngưỡng ngân sách: tính usage, deviation_pct, breach_streak, historical_variance, self_control_index.
 * Output inject vào cognitive_input.threshold_summary để DecisionCore / BrainMode / Narrative đọc.
 */
class BudgetThresholdService
{
    private const SNAPSHOT_LOOKBACK_PERIODS = 12;

    public function __construct() {}

    /**
     * Tổng hợp ngưỡng cho pipeline: mỗi ngưỡng có deviation_pct, breach_streak, historical_variance, self_control_index.
     *
     * @param  array<string>  $linkedAccountNumbers
     * @return array{active_count: int, thresholds: array, aggregate: array{avg_deviation_pct: float|null, max_breach_streak: int, avg_self_control_index: float|null, user_goals_summary: string}}
     */
    public function getThresholdSummaryForUser(int $userId, array $linkedAccountNumbers = []): array
    {
        $thresholds = BudgetThreshold::where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        if ($thresholds->isEmpty()) {
            return $this->emptySummary();
        }

        $now = Carbon::now();
        $items = [];
        $totalDeviation = 0.0;
        $totalSpi = 0.0;
        $deviationCount = 0;
        $spiCount = 0;
        $maxBreachStreak = 0;
        $goalLabels = [];

        foreach ($thresholds as $t) {
            [$start, $end, $periodKey] = $this->resolvePeriod($t, $now);
            if ($start === null || $end === null) {
                continue;
            }

            $spent = $this->sumSpentForThreshold($userId, $t, $start, $end, $linkedAccountNumbers);
            $limit = (int) $t->amount_limit_vnd;
            $deviationPct = $limit > 0 ? (($spent - $limit) / $limit) * 100 : 0.0;
            $breached = $spent > $limit;

            $snapshots = $this->loadSnapshotsForStreakAndVariance($t->id);
            $breachStreak = $this->computeBreachStreak($snapshots, $breached, $periodKey);
            $historicalVariance = $this->computeHistoricalVariance($snapshots, $limit);
            $selfControlIndex = $this->computeSelfControlIndex($snapshots);

            $this->upsertSnapshot($t->id, $periodKey, $start, $end, $limit, (int) round($spent), $deviationPct, $breached);
            $this->recordEvent($t->user_id, $t->id, 'period_evaluated', [
                'period_key' => $periodKey,
                'total_spent_vnd' => (int) round($spent),
                'amount_limit_vnd' => $limit,
                'deviation_pct' => round($deviationPct, 2),
                'breached' => $breached,
            ]);

            $categoryLabels = $this->resolveCategoryLabels($t->category_bindings ?? []);

            $items[] = [
                'threshold_id' => $t->id,
                'name' => $t->name,
                'limit_vnd' => $limit,
                'spent_vnd' => (int) round($spent),
                'deviation_pct' => round($deviationPct, 1),
                'breached' => $breached,
                'breach_streak' => $breachStreak,
                'historical_variance' => $historicalVariance,
                'self_control_index' => $selfControlIndex,
                'category_labels' => $categoryLabels,
                'period_key' => $periodKey,
            ];

            $goalLabels[] = $t->name;
            $totalDeviation += $deviationPct;
            $deviationCount++;
            if ($selfControlIndex !== null) {
                $totalSpi += $selfControlIndex;
                $spiCount++;
            }
            if ($breachStreak > $maxBreachStreak) {
                $maxBreachStreak = $breachStreak;
            }
        }

        $avgDeviation = $deviationCount > 0 ? $totalDeviation / $deviationCount : null;
        $avgSpi = $spiCount > 0 ? $totalSpi / $spiCount : null;
        $userGoalsSummary = implode(', ', array_slice($goalLabels, 0, 5));
        if (count($goalLabels) > 5) {
            $userGoalsSummary .= ' …';
        }

        return [
            'active_count' => count($items),
            'thresholds' => $items,
            'aggregate' => [
                'avg_deviation_pct' => $avgDeviation !== null ? round($avgDeviation, 1) : null,
                'max_breach_streak' => $maxBreachStreak,
                'avg_self_control_index' => $avgSpi !== null ? round($avgSpi, 1) : null,
                'user_goals_summary' => $userGoalsSummary,
            ],
        ];
    }

    private function emptySummary(): array
    {
        return [
            'active_count' => 0,
            'thresholds' => [],
            'aggregate' => [
                'avg_deviation_pct' => null,
                'max_breach_streak' => 0,
                'avg_self_control_index' => null,
                'user_goals_summary' => '',
            ],
        ];
    }

    private function resolvePeriod(BudgetThreshold $t, Carbon $now): array
    {
        if ($t->period_type === 'custom' && $t->period_start && $t->period_end) {
            $start = Carbon::parse($t->period_start)->startOfDay();
            $end = Carbon::parse($t->period_end)->endOfDay();
            $periodKey = $start->format('Y-m-d') . '_' . $end->format('Y-m-d');
            return [$start, $end, $periodKey];
        }
        $year = (int) ($t->year ?? $now->year);
        $month = (int) ($t->month ?? $now->month);
        $start = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();
        $periodKey = $start->format('Y-m');
        return [$start, $end, $periodKey];
    }

    private function sumSpentForThreshold(int $userId, BudgetThreshold $t, Carbon $start, Carbon $end, array $linkedAccountNumbers): float
    {
        $bindings = $t->category_bindings ?? [];
        if (empty($bindings)) {
            return 0.0;
        }

        $userCategoryIds = [];
        $systemCategoryIds = [];
        foreach ($bindings as $b) {
            if (is_array($b)) {
                $type = $b['type'] ?? '';
                $id = isset($b['id']) ? (int) $b['id'] : 0;
                if ($type === 'user_category' && $id > 0) {
                    $userCategoryIds[] = $id;
                } elseif ($type === 'system_category' && $id > 0) {
                    $systemCategoryIds[] = $id;
                }
            }
        }

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

        $query->where(function ($q) use ($userCategoryIds, $systemCategoryIds) {
            if (! empty($userCategoryIds)) {
                $q->orWhereIn('user_category_id', $userCategoryIds);
            }
            if (! empty($systemCategoryIds)) {
                $q->orWhereIn('system_category_id', $systemCategoryIds);
            }
        });

        $sum = $query->sum(\DB::raw('ABS(amount)'));
        return (float) $sum;
    }

    /** @return Collection<int, BudgetThresholdSnapshot> */
    private function loadSnapshotsForStreakAndVariance(int $thresholdId): Collection
    {
        return BudgetThresholdSnapshot::where('budget_threshold_id', $thresholdId)
            ->orderByDesc('period_key')
            ->limit(self::SNAPSHOT_LOOKBACK_PERIODS)
            ->get();
    }

    private function computeBreachStreak(Collection $snapshots, bool $currentBreached, string $currentPeriodKey): int
    {
        if ($currentBreached) {
            $streak = 1;
            foreach ($snapshots as $s) {
                if (($s->period_key ?? '') === $currentPeriodKey) {
                    continue;
                }
                if ($s->breached) {
                    $streak++;
                } else {
                    break;
                }
            }
            return $streak;
        }
        return 0;
    }

    /** Phương sai của tỷ lệ spent/limit qua các kỳ (đơn vị: phần trăm). */
    private function computeHistoricalVariance(Collection $snapshots, int $limit): ?float
    {
        if ($snapshots->isEmpty() || $limit <= 0) {
            return null;
        }
        $ratios = [];
        foreach ($snapshots as $s) {
            $spent = (int) ($s->total_spent_vnd ?? 0);
            $ratios[] = $spent / $limit;
        }
        $v = $this->variance($ratios);
        return $v !== null ? round($v * 100, 1) : null;
    }

    /** Chỉ số tự kiểm soát 0–100: càng ít vượt ngưỡng càng cao. */
    private function computeSelfControlIndex(Collection $snapshots): ?float
    {
        if ($snapshots->isEmpty()) {
            return null;
        }
        $breachedCount = $snapshots->filter(fn ($s) => $s->breached)->count();
        $rate = $breachedCount / $snapshots->count();
        return round(100 * (1 - $rate), 1);
    }

    private function variance(array $values): ?float
    {
        $n = count($values);
        if ($n < 2) {
            return null;
        }
        $mean = array_sum($values) / $n;
        $sumSq = 0.0;
        foreach ($values as $v) {
            $sumSq += ($v - $mean) ** 2;
        }
        return (float) ($sumSq / ($n - 1));
    }

    private function recordEvent(int $userId, ?int $thresholdId, string $eventType, array $payload = []): void
    {
        BudgetThresholdEvent::create([
            'user_id' => $userId,
            'budget_threshold_id' => $thresholdId,
            'event_type' => $eventType,
            'payload' => $payload,
        ]);
    }

    private function upsertSnapshot(int $thresholdId, string $periodKey, Carbon $start, Carbon $end, int $limit, int $spent, float $deviationPct, bool $breached): void
    {
        BudgetThresholdSnapshot::updateOrCreate(
            [
                'budget_threshold_id' => $thresholdId,
                'period_key' => $periodKey,
            ],
            [
                'period_start' => $start,
                'period_end' => $end,
                'amount_limit_vnd' => $limit,
                'total_spent_vnd' => $spent,
                'deviation_pct' => $deviationPct,
                'breached' => $breached,
            ]
        );
    }

    private function resolveCategoryLabels(array $bindings): array
    {
        $labels = [];
        foreach ($bindings as $b) {
            if (! is_array($b)) {
                continue;
            }
            $type = $b['type'] ?? '';
            $id = isset($b['id']) ? (int) $b['id'] : 0;
            if ($type === 'user_category' && $id > 0) {
                $c = \App\Models\UserCategory::find($id);
                $labels[] = $c ? $c->name : 'User#' . $id;
            } elseif ($type === 'system_category' && $id > 0) {
                $c = \App\Models\SystemCategory::find($id);
                $labels[] = $c ? $c->name : 'System#' . $id;
            }
        }
        return $labels;
    }
}
