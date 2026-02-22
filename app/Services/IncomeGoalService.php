<?php

namespace App\Services;

use App\Models\IncomeGoal;
use App\Models\IncomeGoalEvent;
use App\Models\IncomeGoalSnapshot;
use App\Models\TransactionHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Mục tiêu thu: theo dõi thu theo danh mục (type IN), so sánh với target, achievement_pct, met, achievement_streak.
 */
class IncomeGoalService
{
    private const SNAPSHOT_LOOKBACK_PERIODS = 12;

    public function __construct() {}

    /**
     * @param  array<string>  $linkedAccountNumbers
     * @return array{active_count: int, goals: array, aggregate: array{avg_achievement_pct: float|null, max_achievement_streak: int, user_goals_summary: string}}
     */
    public function getGoalSummaryForUser(int $userId, array $linkedAccountNumbers = []): array
    {
        $goals = IncomeGoal::where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        if ($goals->isEmpty()) {
            return $this->emptySummary();
        }

        $now = Carbon::now();
        $items = [];
        $totalAchievement = 0.0;
        $achievementCount = 0;
        $maxAchievementStreak = 0;
        $goalLabels = [];

        foreach ($goals as $g) {
            [$start, $end, $periodKey] = $this->resolvePeriod($g, $now);
            if ($start === null || $end === null) {
                continue;
            }

            $earned = $this->sumEarnedForGoal($userId, $g, $start, $end, $linkedAccountNumbers);
            $expenseBindings = $g->expense_category_bindings ?? [];
            if (! empty($expenseBindings)) {
                $spent = $this->sumExpenseForGoal($userId, $g, $start, $end, $linkedAccountNumbers);
                $earned = $earned - $spent;
            }
            $target = (int) $g->amount_target_vnd;
            $achievementPct = $target > 0 ? ($earned / $target) * 100 : 0.0;
            $met = $earned >= $target;

            $snapshots = $this->loadSnapshotsForStreak($g->id);
            $achievementStreak = $this->computeAchievementStreak($snapshots, $met, $periodKey);

            $this->upsertSnapshot($g->id, $periodKey, $start, $end, $target, (int) round($earned), round($achievementPct, 2), $met);
            $this->recordEvent($g->user_id, $g->id, 'period_evaluated', [
                'period_key' => $periodKey,
                'total_earned_vnd' => (int) round($earned),
                'amount_target_vnd' => $target,
                'achievement_pct' => round($achievementPct, 2),
                'met' => $met,
            ]);

            $categoryLabels = $this->resolveCategoryLabels($g->category_bindings ?? []);

            $items[] = [
                'name' => $g->name,
                'target_vnd' => $target,
                'earned_vnd' => (int) round($earned),
                'achievement_pct' => round($achievementPct, 1),
                'met' => $met,
                'achievement_streak' => $achievementStreak,
                'category_labels' => $categoryLabels,
                'period_key' => $periodKey,
            ];

            $goalLabels[] = $g->name;
            $totalAchievement += $achievementPct;
            $achievementCount++;
            if ($achievementStreak > $maxAchievementStreak) {
                $maxAchievementStreak = $achievementStreak;
            }
        }

        $avgAchievementPct = $achievementCount > 0 ? $totalAchievement / $achievementCount : null;
        $userGoalsSummary = implode(', ', array_slice($goalLabels, 0, 5));
        if (count($goalLabels) > 5) {
            $userGoalsSummary .= ' …';
        }

        return [
            'active_count' => count($items),
            'goals' => $items,
            'aggregate' => [
                'avg_achievement_pct' => $avgAchievementPct !== null ? round($avgAchievementPct, 1) : null,
                'max_achievement_streak' => $maxAchievementStreak,
                'user_goals_summary' => $userGoalsSummary,
            ],
        ];
    }

    private function emptySummary(): array
    {
        return [
            'active_count' => 0,
            'goals' => [],
            'aggregate' => [
                'avg_achievement_pct' => null,
                'max_achievement_streak' => 0,
                'user_goals_summary' => '',
            ],
        ];
    }

    private function resolvePeriod(IncomeGoal $g, Carbon $now): array
    {
        if ($g->period_type === 'custom' && $g->period_start && $g->period_end) {
            $start = Carbon::parse($g->period_start)->startOfDay();
            $end = Carbon::parse($g->period_end)->endOfDay();
            $periodKey = $start->format('Y-m-d') . '_' . $end->format('Y-m-d');
            return [$start, $end, $periodKey];
        }
        $year = (int) ($g->year ?? $now->year);
        $month = (int) ($g->month ?? $now->month);
        $start = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();
        $periodKey = $start->format('Y-m');
        return [$start, $end, $periodKey];
    }

    private function sumEarnedForGoal(int $userId, IncomeGoal $g, Carbon $start, Carbon $end, array $linkedAccountNumbers): float
    {
        $bindings = $g->category_bindings ?? [];
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
            ->where('type', 'IN')
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

    private function sumExpenseForGoal(int $userId, IncomeGoal $g, Carbon $start, Carbon $end, array $linkedAccountNumbers): float
    {
        $bindings = $g->expense_category_bindings ?? [];
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

    /** @return Collection<int, IncomeGoalSnapshot> */
    private function loadSnapshotsForStreak(int $goalId): Collection
    {
        return IncomeGoalSnapshot::where('income_goal_id', $goalId)
            ->orderByDesc('period_key')
            ->limit(self::SNAPSHOT_LOOKBACK_PERIODS)
            ->get();
    }

    private function computeAchievementStreak(Collection $snapshots, bool $currentMet, string $currentPeriodKey): int
    {
        if (! $currentMet) {
            return 0;
        }
        $streak = 1;
        foreach ($snapshots as $s) {
            if (($s->period_key ?? '') === $currentPeriodKey) {
                continue;
            }
            if ($s->met) {
                $streak++;
            } else {
                break;
            }
        }
        return $streak;
    }

    private function recordEvent(int $userId, ?int $goalId, string $eventType, array $payload = []): void
    {
        IncomeGoalEvent::create([
            'user_id' => $userId,
            'income_goal_id' => $goalId,
            'event_type' => $eventType,
            'payload' => $payload,
        ]);
    }

    private function upsertSnapshot(int $goalId, string $periodKey, Carbon $start, Carbon $end, int $target, int $earned, float $achievementPct, bool $met): void
    {
        IncomeGoalSnapshot::updateOrCreate(
            [
                'income_goal_id' => $goalId,
                'period_key' => $periodKey,
            ],
            [
                'period_start' => $start,
                'period_end' => $end,
                'amount_target_vnd' => $target,
                'total_earned_vnd' => $earned,
                'achievement_pct' => $achievementPct,
                'met' => $met,
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
