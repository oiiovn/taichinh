<?php

namespace App\Services\Classification;

use App\Models\GlobalMerchantPattern;
use App\Models\SystemCategory;
use App\Models\TransactionHistory;
use App\Models\UserBehaviorPattern;
use App\Models\UserCategory;
use App\Models\UserMerchantRule;
use App\Models\UserRecurringPattern;
use App\Services\MerchantKeyNormalizer;
use App\Services\TransactionClassificationGptService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class CandidateCollector
{
    private const BEHAVIOR_CONFIDENCE_THRESHOLD = 0.6;

    public function __construct(
        private MerchantKeyNormalizer $normalizer,
        private TransactionClassificationGptService $gptService
    ) {}

    /**
     * @return array<int, array{source: string, user_category_id: int|null, system_category_id: int|null, raw_confidence: float, stability_score: float, evidence_score: float, risk_adjustment: float, pattern_id: int|null, pattern_model: string|null, reason: string|null}>
     */
    public function collect(TransactionHistory $transaction): array
    {
        $candidates = [];
        $group = $transaction->merchant_group ?: $transaction->merchant_key;

        $rule = $this->findRuleCandidate($transaction);
        if ($rule !== null) {
            $candidates[] = $rule;
        }

        if (! $transaction->amount_bucket) {
            $transaction->amount_bucket = TransactionHistory::resolveAmountBucket((int) round((float) $transaction->amount));
            $transaction->save();
        }

        $behavior = $this->findBehaviorCandidate($transaction, $group);
        if ($behavior !== null) {
            $candidates[] = $behavior;
        }

        $recurring = $this->findRecurringCandidate($transaction, $group);
        if ($recurring !== null) {
            $candidates[] = $recurring;
        }

        $global = $this->findGlobalCandidate($group, $transaction->type, $transaction->amount_bucket);
        if ($global !== null) {
            $candidates[] = $global;
        }

        $gpt = $this->findGptCandidate($transaction, $group);
        if ($gpt !== null) {
            $candidates[] = $gpt;
        }

        $keyword = $this->findKeywordFallbackCandidate($transaction);
        if ($keyword !== null) {
            $candidates[] = $keyword;
        }

        return $candidates;
    }

    private function findKeywordFallbackCandidate(TransactionHistory $transaction): ?array
    {
        $cfg = config('classification.keyword_fallback', []);
        if (empty($cfg['enabled']) || empty($cfg['rules'])) {
            return null;
        }
        $search = $this->normalizeForKeyword(($transaction->description ?? '') . ' ' . ($transaction->merchant_key ?? ''));
        $direction = $transaction->type === 'IN' ? 'IN' : 'OUT';
        $confidence = (float) ($cfg['confidence'] ?? 0.6);

        foreach ($cfg['rules'] as $rule) {
            $ruleDir = $rule['direction'] ?? null;
            if ($ruleDir !== null && $ruleDir !== $direction) {
                continue;
            }
            foreach ($rule['keywords'] as $kw) {
                if (mb_strpos($search, $this->normalizeForKeyword($kw)) !== false) {
                    $category = SystemCategory::where('name', $rule['category'])->first();
                    if ($category) {
                        return [
                            'source' => 'ai',
                            'user_category_id' => UserCategory::where('user_id', $transaction->user_id)
                                ->where('based_on_system_category_id', $category->id)
                                ->value('id'),
                            'system_category_id' => $category->id,
                            'raw_confidence' => $confidence,
                            'stability_score' => $confidence * 0.85,
                            'evidence_score' => 0.5,
                            'risk_adjustment' => 0.0,
                            'pattern_id' => null,
                            'pattern_model' => null,
                            'reason' => 'keyword_fallback',
                        ];
                    }
                }
            }
        }
        return null;
    }

    private function normalizeForKeyword(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = preg_replace('/[^a-z0-9\s]/u', ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s);
        return trim($s);
    }

    private function findRuleCandidate(TransactionHistory $transaction): ?array
    {
        $rule = UserMerchantRule::where('user_id', $transaction->user_id)
            ->where('merchant_key', $transaction->merchant_key)
            ->first();

        if (! $rule && $transaction->merchant_key !== 'unknown') {
            $normalizedKey = $this->normalizer->normalizeKeyForMatch($transaction->merchant_key);
            $rules = UserMerchantRule::where('user_id', $transaction->user_id)->get();
            foreach ($rules as $r) {
                if ($this->normalizer->normalizeKeyForMatch($r->merchant_key) === $normalizedKey) {
                    $rule = $r;
                    break;
                }
            }
            if (! $rule) {
                $pattern = $this->normalizer->keyPatternForMatch($transaction->merchant_key);
                $candidate = null;
                foreach ($rules as $r) {
                    if ($this->normalizer->keyPatternForMatch($r->merchant_key) === $pattern) {
                        if ($candidate === null || ($r->confirmed_count ?? 0) > ($candidate->confirmed_count ?? 0)) {
                            $candidate = $r;
                        }
                    }
                }
                if ($candidate !== null) {
                    $rule = $candidate;
                }
            }
        }

        if ($rule === null) {
            return null;
        }

        return [
            'source' => 'rule',
            'user_category_id' => $rule->mapped_user_category_id,
            'system_category_id' => null,
            'raw_confidence' => 1.0,
            'stability_score' => 1.0,
            'evidence_score' => min(1.0, 0.5 + ($rule->confirmed_count ?? 0) * 0.05),
            'risk_adjustment' => 0.0,
            'pattern_id' => null,
            'pattern_model' => null,
            'reason' => 'user_rule',
        ];
    }

    private function findBehaviorCandidate(TransactionHistory $transaction, string $group): ?array
    {
        $behavior = UserBehaviorPattern::where('user_id', $transaction->user_id)
            ->where('merchant_group', $group)
            ->where('direction', $transaction->type)
            ->where('amount_bucket', $transaction->amount_bucket)
            ->where('confidence_score', '>=', self::BEHAVIOR_CONFIDENCE_THRESHOLD)
            ->whereNotNull('user_category_id')
            ->orderByDesc('usage_count')
            ->first();

        if ($behavior === null || ! $behavior->user_category_id) {
            return null;
        }

        return [
            'source' => 'behavior',
            'user_category_id' => $behavior->user_category_id,
            'system_category_id' => null,
            'raw_confidence' => (float) $behavior->confidence_score,
            'stability_score' => min(1.0, (float) $behavior->confidence_score + 0.1),
            'evidence_score' => min(1.0, 0.3 + $behavior->usage_count * 0.02),
            'risk_adjustment' => 0.0,
            'pattern_id' => $behavior->id,
            'pattern_model' => 'UserBehaviorPattern',
            'reason' => 'behavior_pattern',
        ];
    }

    private function findRecurringCandidate(TransactionHistory $transaction, string $group): ?array
    {
        $cfg = config('classification.recurring', []);
        $threshold = $cfg['match_confidence_threshold'] ?? 0.5;

        $candidates = UserRecurringPattern::where('user_id', $transaction->user_id)
            ->where('merchant_group', $group)
            ->where('direction', $transaction->type)
            ->where('status', UserRecurringPattern::STATUS_ACTIVE)
            ->whereNotNull('user_category_id')
            ->orderByDesc('confidence_score')
            ->get();

        $txDate = $transaction->transaction_date ? Carbon::parse($transaction->transaction_date) : now();
        $amount = (float) $transaction->amount;

        foreach ($candidates as $pattern) {
            if (! $pattern->matchesTransaction($amount, $txDate)) {
                continue;
            }
            $confidence = $pattern->interval_std > 0 || isset($pattern->amount_cv)
                ? $pattern->getCompositeConfidence()
                : (float) $pattern->confidence_score;
            if ($confidence < $threshold) {
                continue;
            }
            $dateDrift = 0.0;
            if ($pattern->next_expected_at !== null) {
                $expected = Carbon::parse($pattern->next_expected_at)->startOfDay();
                $dateDrift = abs($txDate->copy()->startOfDay()->diffInDays($expected, false));
            }
            return [
                'source' => 'recurring',
                'user_category_id' => $pattern->user_category_id,
                'system_category_id' => null,
                'raw_confidence' => $confidence,
                'stability_score' => $confidence,
                'evidence_score' => min(1.0, 0.4 + $pattern->match_count * 0.05),
                'risk_adjustment' => $dateDrift > 5 ? 0.15 : 0.0,
                'pattern_id' => $pattern->id,
                'pattern_model' => 'UserRecurringPattern',
                'reason' => 'recurring_match',
            ];
        }

        return null;
    }

    private function findGlobalCandidate(string $group, string $direction, string $amountBucket): ?array
    {
        $pattern = GlobalMerchantPattern::where('merchant_group', $group)
            ->where(function ($q) use ($direction, $amountBucket) {
                $q->where(function ($q2) use ($direction, $amountBucket) {
                    $q2->where('direction', $direction)->where('amount_bucket', $amountBucket);
                })->orWhere(function ($q2) {
                    $q2->where('direction', '')->where('amount_bucket', '');
                });
            })
            ->orderByRaw('CASE WHEN direction = ? AND amount_bucket = ? THEN 0 ELSE 1 END', [$direction, $amountBucket])
            ->orderByDesc('usage_count')
            ->limit(1)
            ->get()
            ->first();

        if ($pattern === null) {
            return null;
        }

        $confidence = $pattern->usage_count > 0
            ? $pattern->getConfidenceFromAccuracy()
            : (float) $pattern->confidence_score;
        if ($confidence < 0.5) {
            return null;
        }

        return [
            'source' => 'global',
            'user_category_id' => null,
            'system_category_id' => $pattern->system_category_id,
            'raw_confidence' => $confidence,
            'stability_score' => $confidence,
            'evidence_score' => min(1.0, 0.3 + $pattern->usage_count * 0.01),
            'risk_adjustment' => 0.0,
            'pattern_id' => $pattern->id,
            'pattern_model' => 'GlobalMerchantPattern',
            'reason' => 'global_pattern',
        ];
    }

    private function findGptCandidate(TransactionHistory $transaction, string $group): ?array
    {
        $result = $this->callGptLayerWithDecay($transaction, $group);
        if ($result === null || (empty($result['user_category_id']) && empty($result['system_category_id']))) {
            return null;
        }
        $confidence = $result['effective_confidence'] ?? $result['confidence'] ?? 0.5;

        return [
            'source' => 'ai',
            'user_category_id' => $result['user_category_id'] ?? null,
            'system_category_id' => $result['system_category_id'] ?? null,
            'raw_confidence' => $confidence,
            'stability_score' => $confidence * 0.9,
            'evidence_score' => 0.5,
            'risk_adjustment' => 0.0,
            'pattern_id' => null,
            'pattern_model' => null,
            'reason' => $result['reason'] ?? 'gpt',
        ];
    }

    private function callGptLayerWithDecay(TransactionHistory $transaction, string $group): ?array
    {
        $dir = $transaction->type;
        $bucket = $transaction->amount_bucket ?: TransactionHistory::resolveAmountBucket((int) round((float) $transaction->amount));
        $semanticHash = md5(mb_substr($transaction->description ?? '', 0, 100));
        $version = 2;
        $cacheKey = 'ai_classified_v' . $version . ':' . md5($group . ':' . $dir . ':' . $bucket . ':' . $semanticHash . ':' . (string) $transaction->user_id);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            $lambda = (float) config('classification.v3.cache_decay_lambda', 0.05);
            $createdAt = $cached['cache_created_at'] ?? null;
            $days = $createdAt ? now()->diffInDays(Carbon::parse($createdAt), false) : 0;
            $effective = ($cached['cache_confidence'] ?? $cached['confidence'] ?? 0.5) * exp(-$lambda * max(0, $days));
            $cached['effective_confidence'] = $effective;
            return $cached;
        }

        $desc = $transaction->description ? mb_substr($transaction->description, 0, 300) : null;
        $amount = $transaction->amount !== null ? (float) $transaction->amount : null;
        $out = $this->gptService->classify($group, $dir, $bucket, $desc, $amount, $transaction->user_id);
        if ($out === null) {
            return null;
        }

        $result = [
            'user_category_id' => $out['user_category_id'] ?? null,
            'system_category_id' => $out['system_category_id'] ?? null,
            'confidence' => $out['confidence'] ?? 0.5,
            'reason' => $out['reason'] ?? '',
            'cache_confidence' => $out['confidence'] ?? 0.5,
            'cache_created_at' => now()->toIso8601String(),
        ];
        $ttlDays = (int) config('classification.gpt.cache_ttl_days', 7);
        Cache::put($cacheKey, $result, $ttlDays * 86400);

        $result['effective_confidence'] = $result['cache_confidence'];
        return $result;
    }
}
