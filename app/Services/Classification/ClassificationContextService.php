<?php

namespace App\Services\Classification;

use App\Models\TransactionHistory;
use Illuminate\Support\Facades\DB;

class ClassificationContextService
{
    /**
     * Z-score amount vs merchant (group + direction) mean/stddev.
     * Return [is_anomaly => bool, z_score => float, reduce_pct => float].
     */
    public function getAnomalyAdjustment(TransactionHistory $transaction): array
    {
        $group = $transaction->merchant_group ?: $transaction->merchant_key;
        $direction = $transaction->type;
        $amount = abs((float) $transaction->amount);

        $stats = DB::table('transaction_history')
            ->where('merchant_group', $group)
            ->where('type', $direction)
            ->whereNotNull('amount')
            ->selectRaw('AVG(ABS(amount)) as mean, STDDEV(ABS(amount)) as stddev, COUNT(*) as cnt')
            ->first();

        if ($stats === null || (float) ($stats->cnt ?? 0) < 3 || (float) ($stats->stddev ?? 0) <= 0) {
            return ['is_anomaly' => false, 'z_score' => 0.0, 'reduce_pct' => 0.0];
        }

        $mean = (float) $stats->mean;
        $stddev = (float) $stats->stddev;
        $z = $mean > 0 ? ($amount - $mean) / $stddev : 0.0;

        $threshold = (float) config('classification.v3.anomaly_z_threshold', 2.5);
        $reducePct = (float) config('classification.v3.anomaly_global_reduce_pct', 0.20);

        return [
            'is_anomaly' => abs($z) > $threshold,
            'z_score' => $z,
            'reduce_pct' => abs($z) > $threshold ? $reducePct : 0.0,
        ];
    }

    /**
     * Category entropy for merchant_group: -sum(p_i * log(p_i)).
     * High entropy = merchant thường thuộc nhiều category → giảm trust.
     */
    public function getMerchantEntropy(string $merchantGroup, ?int $userId = null): float
    {
        $q = DB::table('transaction_history')
            ->where('merchant_group', $merchantGroup)
            ->whereNotNull('system_category_id');
        if ($userId !== null) {
            $q->where('user_id', $userId);
        }
        $counts = $q->selectRaw('system_category_id, COUNT(*) as c')
            ->groupBy('system_category_id')
            ->pluck('c', 'system_category_id')
            ->all();

        $total = array_sum($counts);
        if ($total <= 0) {
            return 0.0;
        }

        $entropy = 0.0;
        foreach ($counts as $c) {
            $p = $c / $total;
            if ($p > 0) {
                $entropy -= $p * log($p);
            }
        }
        $maxEntropy = log(count($counts));
        if ($maxEntropy <= 0) {
            return 0.0;
        }
        return (float) ($entropy / $maxEntropy);
    }

    /**
     * Contextual adjustment factor from runway, debt_pressure (0..1).
     * Returns multiplier for contextual_alignment component (1.0 = no change).
     */
    public function getContextualAlignmentMultiplier(TransactionHistory $transaction): float
    {
        return 1.0;
    }
}
