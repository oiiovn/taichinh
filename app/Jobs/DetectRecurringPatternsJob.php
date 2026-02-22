<?php

namespace App\Jobs;

use App\Models\TransactionHistory;
use App\Models\UserRecurringPattern;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DetectRecurringPatternsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?int $userId = null
    ) {}

    public function handle(): void
    {
        $cfg = config('classification.recurring', []);
        $minCount = $cfg['min_transactions'] ?? 3;
        $maxAnalyze = $cfg['max_transactions_analyze'] ?? 12;
        $intervalMin = $cfg['interval_days_min'] ?? 25;
        $intervalMax = $cfg['interval_days_max'] ?? 35;
        $intervalStdMax = $cfg['interval_std_max'] ?? 5;
        $amountCvMax = $cfg['amount_cv_max'] ?? 0.10;

        $query = TransactionHistory::query()
            ->whereNotNull('user_category_id')
            ->whereNotNull('merchant_group')
            ->where('merchant_group', '!=', '')
            ->whereNotNull('transaction_date')
            ->whereIn('type', ['IN', 'OUT']);

        if ($this->userId !== null) {
            $query->where('user_id', $this->userId);
        }

        $groups = $query->select('user_id', 'merchant_group', 'type')
            ->groupBy('user_id', 'merchant_group', 'type')
            ->get()
            ->map(fn ($r) => ['user_id' => $r->user_id, 'merchant_group' => $r->merchant_group, 'direction' => $r->type])
            ->unique(fn ($r) => $r['user_id'] . '|' . $r['merchant_group'] . '|' . $r['direction'])
            ->values();

        foreach ($groups as $g) {
            $txs = TransactionHistory::where('user_id', $g['user_id'])
                ->where('merchant_group', $g['merchant_group'])
                ->where('type', $g['direction'])
                ->whereNotNull('user_category_id')
                ->whereNotNull('transaction_date')
                ->orderByDesc('transaction_date')
                ->limit($maxAnalyze)
                ->get()
                ->sortBy('transaction_date')
                ->values();

            if ($txs->count() < $minCount) {
                continue;
            }

            $amounts = $txs->map(fn ($t) => (float) $t->amount)->all();
            $dates = $txs->map(fn ($t) => Carbon::parse($t->transaction_date))->all();

            $intervals = [];
            for ($i = 0; $i < count($dates) - 1; $i++) {
                $intervals[] = $dates[$i + 1]->diffInDays($dates[$i]);
            }
            if (count($intervals) < 1) {
                continue;
            }

            $avgInterval = array_sum($intervals) / count($intervals);
            $intervalVariance = array_sum(array_map(fn ($x) => ($x - $avgInterval) ** 2, $intervals)) / count($intervals);
            $intervalStd = $intervalVariance > 0 ? sqrt($intervalVariance) : 0;

            $avgAmount = array_sum($amounts) / count($amounts);
            $amountVariance = array_sum(array_map(fn ($x) => ($x - $avgAmount) ** 2, $amounts)) / count($amounts);
            $amountStd = $amountVariance > 0 ? sqrt($amountVariance) : 0;
            $amountCv = $avgAmount != 0 ? $amountStd / abs($avgAmount) : 0;

            $inRange = $avgInterval >= $intervalMin && $avgInterval <= $intervalMax;
            $intervalStable = $intervalStd <= $intervalStdMax;
            $amountStable = $amountCv <= $amountCvMax;

            if (! $inRange || ! $intervalStable || ! $amountStable) {
                continue;
            }

            $lastTx = $txs->last();
            $categoryId = $lastTx->user_category_id;
            $lastSeen = Carbon::parse($lastTx->transaction_date);
            $nextExpected = $lastSeen->copy()->addDays((int) round($avgInterval));

            $pattern = UserRecurringPattern::updateOrCreate(
                [
                    'user_id' => $g['user_id'],
                    'merchant_group' => $g['merchant_group'],
                    'direction' => $g['direction'],
                ],
                [
                    'avg_amount' => round($avgAmount, 2),
                    'amount_std' => round($amountStd, 2),
                    'avg_interval_days' => round($avgInterval, 2),
                    'interval_std' => round($intervalStd, 2),
                    'user_category_id' => $categoryId,
                    'confidence_score' => min(0.95, 0.5 + count($txs) * 0.05),
                    'last_seen_at' => $lastSeen,
                    'next_expected_at' => $nextExpected,
                    'status' => UserRecurringPattern::STATUS_ACTIVE,
                ]
            );

            if ($pattern->wasRecentlyCreated) {
                $pattern->update(['match_count' => 0]);
            }
        }
    }
}
