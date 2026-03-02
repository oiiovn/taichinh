<?php

namespace App\Services\Classification;

use App\Models\ClassificationAccuracyBySource;
use App\Models\TransactionHistory;

class UnifiedScoringService
{
    public function __construct(
        private ClassificationContextService $contextService
    ) {}

    /**
     * Score each candidate: final_score = 0.4*source_weight + 0.3*historical_accuracy + 0.2*pattern_stability + 0.1*contextual_alignment.
     * Apply anomaly reduction (global/behavior), entropy discount, recurring date drift.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<int, array<string, mixed>> candidates with final_score, anomaly_flag, entropy
     */
    public function scoreCandidates(array $candidates, TransactionHistory $transaction): array
    {
        if (empty($candidates)) {
            return [];
        }

        $weights = config('classification.v3.unified_confidence', []);
        $wSource = (float) ($weights['source_weight'] ?? 0.4);
        $wAccuracy = (float) ($weights['historical_accuracy'] ?? 0.3);
        $wStability = (float) ($weights['pattern_stability'] ?? 0.2);
        $wContext = (float) ($weights['contextual_alignment'] ?? 0.1);

        $sourceWeights = config('classification.v3.source_weights', []);
        $anomaly = $this->contextService->getAnomalyAdjustment($transaction);
        $entropy = $this->contextService->getMerchantEntropy(
            $transaction->merchant_group ?: $transaction->merchant_key,
            $transaction->user_id
        );
        $contextMultiplier = $this->contextService->getContextualAlignmentMultiplier($transaction);

        $scored = [];
        foreach ($candidates as $c) {
            $source = $c['source'] ?? 'ai';
            $sourceWeight = (float) ($sourceWeights[$source] ?? 0.65);

            $historicalAccuracy = $this->getHistoricalAccuracyForSource($transaction->user_id, $source);

            $patternStability = (float) ($c['stability_score'] ?? 0.5);
            $evidenceScore = (float) ($c['evidence_score'] ?? 0.5);
            $riskAdjustment = (float) ($c['risk_adjustment'] ?? 0);

            $contextualAlignment = $evidenceScore * $contextMultiplier * (1.0 - $entropy * 0.5);

            $rawScore = $wSource * $sourceWeight
                + $wAccuracy * $historicalAccuracy
                + $wStability * ($patternStability - $riskAdjustment)
                + $wContext * $contextualAlignment;

            if ($anomaly['is_anomaly'] && in_array($source, ['global', 'behavior'], true)) {
                $rawScore *= (1.0 - $anomaly['reduce_pct']);
            }

            $c['final_score'] = max(0.0, min(1.0, $rawScore));
            $c['candidate_scores'] = [
                'source_weight' => $sourceWeight,
                'historical_accuracy' => $historicalAccuracy,
                'pattern_stability' => $patternStability - $riskAdjustment,
                'contextual_alignment' => $contextualAlignment,
            ];
            $scored[] = $c;
        }

        return [
            'candidates' => $scored,
            'anomaly_flag' => $anomaly['is_anomaly'],
            'anomaly_z_score' => $anomaly['z_score'],
            'entropy' => $entropy,
        ];
    }

    private function getHistoricalAccuracyForSource(int $userId, string $source): float
    {
        $row = ClassificationAccuracyBySource::where('user_id', $userId)
            ->where('source', $source)
            ->first();
        if ($row === null) {
            return 1.0;
        }
        return (float) $row->accuracy;
    }

    /**
     * Chọn candidate có final_score cao nhất và có category.
     */
    public function selectBest(array $scoredCandidates): ?array
    {
        $best = null;
        $bestScore = -1.0;
        foreach ($scoredCandidates as $c) {
            $hasCategory = ! empty($c['user_category_id']) || ! empty($c['system_category_id']);
            if (! $hasCategory) {
                continue;
            }
            $score = (float) ($c['final_score'] ?? 0);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $c;
            }
        }
        return $best;
    }
}
