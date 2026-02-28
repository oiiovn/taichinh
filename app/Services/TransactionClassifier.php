<?php

namespace App\Services;

use App\Jobs\UpdateGlobalMerchantPatternJob;
use App\Models\GlobalMerchantPattern;
use App\Models\TransactionHistory;
use App\Models\UserBehaviorPattern;
use App\Models\UserCategory;
use App\Models\UserMerchantRule;
use App\Models\UserRecurringPattern;
use App\Models\SystemCategory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class TransactionClassifier
{
    private const BEHAVIOR_CONFIDENCE_THRESHOLD = 0.6;

    private const GLOBAL_CONFIDENCE_THRESHOLD = 0.9;

    private static function gptTeachThreshold(): float
    {
        return (float) config('classification.gpt.confidence_threshold', 0.7);
    }

    private static function gptCacheDays(): int
    {
        return (int) config('classification.gpt.cache_ttl_days', 7);
    }

    /** Nguồn phân loại (feedback loop). */
    public const SOURCE_RULE = 'rule';
    public const SOURCE_BEHAVIOR = 'behavior';
    public const SOURCE_RECURRING = 'recurring';
    public const SOURCE_GLOBAL = 'global';
    public const SOURCE_AI = 'ai';

    public function classify(TransactionHistory $transaction): void
    {
        $normalizer = app(MerchantKeyNormalizer::class);
        if (! $transaction->merchant_key) {
            $pair = $normalizer->normalizeWithGroup($transaction->description);
            $transaction->merchant_key = $pair['merchant_key'];
            $transaction->merchant_group = $pair['merchant_group'];
            $transaction->save();
        }

        // 1. User Rule: exact merchant_key rồi thử match na ná (key chuẩn hóa bỏ số)
        $rule = UserMerchantRule::where('user_id', $transaction->user_id)
            ->where('merchant_key', $transaction->merchant_key)
            ->first();

        if (! $rule && $transaction->merchant_key !== 'unknown') {
            $normalizedKey = $normalizer->normalizeKeyForMatch($transaction->merchant_key);
            $rules = UserMerchantRule::where('user_id', $transaction->user_id)->get();

            foreach ($rules as $r) {
                if ($normalizer->normalizeKeyForMatch($r->merchant_key) === $normalizedKey) {
                    $rule = $r;
                    break;
                }
            }
            // Cùng cụm (vd. pay...starter gd vs pay...basic gd → pattern "pay gd"): ưu tiên match với nhau
            if (! $rule) {
                $pattern = $normalizer->keyPatternForMatch($transaction->merchant_key);
                $candidate = null;
                foreach ($rules as $r) {
                    if ($normalizer->keyPatternForMatch($r->merchant_key) === $pattern) {
                        if ($candidate === null || ($r->confirmed_count ?? 0) > ($candidate->confirmed_count ?? 0)) {
                            $candidate = $r;
                        }
                    }
                }
                if ($candidate !== null) {
                    $rule = $candidate;
                }
            }
            if ($rule) {
                $transaction->merchant_key = $normalizedKey;
                $transaction->merchant_group = (strpos($normalizedKey, ' ') !== false) ? explode(' ', $normalizedKey)[0] : $normalizedKey;
            }
        }

        if ($rule) {
            $transaction->user_category_id = $rule->mapped_user_category_id;
            $transaction->classification_status = TransactionHistory::CLASSIFICATION_STATUS_RULE;
            $transaction->classification_source = self::SOURCE_RULE;
            $transaction->classification_confidence = 1.0;
            $transaction->save();
            return;
        }

        if (! $transaction->amount_bucket) {
            $transaction->amount_bucket = TransactionHistory::resolveAmountBucket((int) round((float) $transaction->amount));
            $transaction->save();
        }

        // 2. User Behavior Pattern (merchant_group + direction + amount_bucket)
        $group = $transaction->merchant_group ?: $transaction->merchant_key;
        $behavior = UserBehaviorPattern::where('user_id', $transaction->user_id)
            ->where('merchant_group', $group)
            ->where('direction', $transaction->type)
            ->where('amount_bucket', $transaction->amount_bucket)
            ->where('confidence_score', '>=', self::BEHAVIOR_CONFIDENCE_THRESHOLD)
            ->whereNotNull('user_category_id')
            ->orderByDesc('usage_count')
            ->first();

        if ($behavior && $behavior->user_category_id) {
            $transaction->user_category_id = $behavior->user_category_id;
            $transaction->classification_status = TransactionHistory::CLASSIFICATION_STATUS_AUTO;
            $transaction->classification_source = self::SOURCE_BEHAVIOR;
            $transaction->classification_confidence = $behavior->confidence_score;
            $transaction->save();
            return;
        }

        // 3. Recurring Pattern (merchant_group + direction + amount + thời gian)
        $recurring = $this->matchRecurringPattern($transaction, $group);
        if ($recurring) {
            $transaction->user_category_id = $recurring->user_category_id;
            $transaction->classification_status = TransactionHistory::CLASSIFICATION_STATUS_AUTO;
            $transaction->classification_source = self::SOURCE_RECURRING;
            $transaction->classification_confidence = $recurring->confidence_score;
            $transaction->save();
            $this->updateRecurringPatternOnMatch($recurring, $transaction);
            return;
        }

        // 4. Global Pattern (merchant_group + direction + amount_bucket) — không override rule cá nhân
        $pattern = $this->matchGlobalPattern($group, $transaction->type, $transaction->amount_bucket);

        if ($pattern) {
            $transaction->system_category_id = $pattern->system_category_id;
            $userCategoryId = UserCategory::where('user_id', $transaction->user_id)
                ->where('based_on_system_category_id', $pattern->system_category_id)
                ->value('id');
            if ($userCategoryId !== null) {
                $transaction->user_category_id = $userCategoryId;
            }
            $transaction->classification_status = TransactionHistory::CLASSIFICATION_STATUS_AUTO;
            $transaction->classification_source = self::SOURCE_GLOBAL;
            $transaction->classification_confidence = $pattern->confidence_score;
            $transaction->save();
            return;
        }

        // 5. GPT Reasoning Layer — chỉ chạy khi không match Rule/Behavior/Global; có cache 7 ngày
        $result = $this->callGptLayer($transaction);

        $hasValidGpt = ($result['confidence'] ?? 0) >= self::gptTeachThreshold()
            && (! empty($result['user_category_id']) || ! empty($result['system_category_id']));

        if (! $hasValidGpt) {
            $keywordCategory = $this->tryKeywordFallback($transaction);
            if ($keywordCategory !== null) {
                $transaction->system_category_id = $keywordCategory['system_category_id'];
                $userCategoryId = UserCategory::where('user_id', $transaction->user_id)
                    ->where('based_on_system_category_id', $keywordCategory['system_category_id'])
                    ->value('id');
                if ($userCategoryId !== null) {
                    $transaction->user_category_id = $userCategoryId;
                }
                $transaction->classification_status = TransactionHistory::CLASSIFICATION_STATUS_AUTO;
                $transaction->classification_source = self::SOURCE_AI;
                $transaction->classification_confidence = $keywordCategory['confidence'];
                $transaction->classification_version = 1;
                $transaction->save();
                return;
            }
            $transaction->classification_status = TransactionHistory::CLASSIFICATION_STATUS_PENDING;
            $transaction->classification_source = self::SOURCE_AI;
            $transaction->classification_confidence = $result['confidence'] ?? 0;
            $transaction->classification_version = (int) ($result['version'] ?? 1);
            $transaction->save();
            return;
        }

        if (! empty($result['user_category_id'])) {
            $transaction->user_category_id = $result['user_category_id'];
            if (! empty($result['system_category_id'])) {
                $transaction->system_category_id = $result['system_category_id'];
            } else {
                $uc = UserCategory::find($result['user_category_id']);
                if ($uc && $uc->based_on_system_category_id) {
                    $transaction->system_category_id = $uc->based_on_system_category_id;
                }
            }
        } else {
            $transaction->system_category_id = $result['system_category_id'];
            $userCategoryId = UserCategory::where('user_id', $transaction->user_id)
                ->where('based_on_system_category_id', $result['system_category_id'])
                ->value('id');
            if ($userCategoryId !== null) {
                $transaction->user_category_id = $userCategoryId;
            }
        }
        $transaction->classification_status = TransactionHistory::CLASSIFICATION_STATUS_AUTO;
        $transaction->classification_source = self::SOURCE_AI;
        $transaction->classification_confidence = $result['confidence'];
        $transaction->classification_version = (int) ($result['version'] ?? 1);
        $transaction->save();

        $sysCatId = $transaction->system_category_id;
        if ($sysCatId && ($result['confidence'] ?? 0) >= self::gptTeachThreshold()) {
            UpdateGlobalMerchantPatternJob::dispatch(
                $group,
                (int) $sysCatId,
                $transaction->type,
                $transaction->amount_bucket ?? ''
            );
        }
    }

    private function matchRecurringPattern(TransactionHistory $transaction, string $group): ?UserRecurringPattern
    {
        $cfg = config('classification.recurring', []);
        $threshold = $cfg['match_confidence_threshold'] ?? 0.5;

        $candidates = UserRecurringPattern::where('user_id', $transaction->user_id)
            ->where('merchant_group', $group)
            ->where('direction', $transaction->type)
            ->where('status', UserRecurringPattern::STATUS_ACTIVE)
            ->where('confidence_score', '>=', $threshold)
            ->whereNotNull('user_category_id')
            ->orderByDesc('confidence_score')
            ->get();

        $txDate = $transaction->transaction_date ? Carbon::parse($transaction->transaction_date) : now();
        $amount = (float) $transaction->amount;

        foreach ($candidates as $pattern) {
            if ($pattern->matchesTransaction($amount, $txDate)) {
                return $pattern;
            }
        }

        return null;
    }

    private function updateRecurringPatternOnMatch(UserRecurringPattern $pattern, TransactionHistory $transaction): void
    {
        $txDate = $transaction->transaction_date ? Carbon::parse($transaction->transaction_date) : now();
        $pattern->increment('match_count');
        $pattern->update([
            'last_seen_at' => $txDate,
            'next_expected_at' => $txDate->copy()->addDays((int) round((float) $pattern->avg_interval_days)),
            'confidence_score' => min(0.95, $pattern->confidence_score + 0.02),
        ]);
    }

    private function matchGlobalPattern(string $group, string $direction, string $amountBucket): ?GlobalMerchantPattern
    {
        $candidates = GlobalMerchantPattern::where('merchant_group', $group)
            ->where('confidence_score', '>=', self::GLOBAL_CONFIDENCE_THRESHOLD)
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
            ->get();

        return $candidates->first();
    }

    /**
     * GPT layer: cache 7 ngày theo (merchant_group, direction, amount_bucket). Cache hit → dùng kết quả, không gọi API.
     */
    protected function callGptLayer(TransactionHistory $transaction): array
    {
        $group = $transaction->merchant_group ?: $transaction->merchant_key;
        $dir = $transaction->type;
        $bucket = $transaction->amount_bucket ?: TransactionHistory::resolveAmountBucket((int) round((float) $transaction->amount));
        $cacheKey = 'ai_classified:' . md5($group . ':' . $dir . ':' . $bucket . ':' . (string) $transaction->user_id);
        $ttlSeconds = self::gptCacheDays() * 86400;

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && (! empty($cached['system_category_id']) || ! empty($cached['user_category_id']))) {
            return array_merge($cached, ['version' => 1]);
        }

        $result = $this->performGptCall($transaction, $group, $dir, $bucket);
        if (is_array($result) && ($result['confidence'] ?? 0) >= self::gptTeachThreshold() && (! empty($result['system_category_id']) || ! empty($result['user_category_id']))) {
            Cache::put($cacheKey, $result, $ttlSeconds);
        }

        return array_merge($result ?? ['system_category_id' => null, 'confidence' => 0], ['version' => 1]);
    }

    /** Khi GPT không trả về: thử match từ khóa (vd. "tien nha" → Hóa đơn & tiện ích). */
    private function tryKeywordFallback(TransactionHistory $transaction): ?array
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
                        return ['system_category_id' => $category->id, 'confidence' => $confidence];
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

    protected function performGptCall(TransactionHistory $transaction, string $group, string $direction, string $amountBucket): ?array
    {
        $service = app(TransactionClassificationGptService::class);
        $desc = $transaction->description ? mb_substr($transaction->description, 0, 300) : null;
        $amount = $transaction->amount !== null ? (float) $transaction->amount : null;
        $out = $service->classify($group, $direction, $amountBucket, $desc, $amount, $transaction->user_id);

        if ($out === null) {
            return ['system_category_id' => null, 'confidence' => 0];
        }

        $result = [
            'confidence' => $out['confidence'],
            'reason' => $out['reason'] ?? '',
        ];
        if (! empty($out['user_category_id'])) {
            $result['user_category_id'] = $out['user_category_id'];
        }
        if (isset($out['system_category_id'])) {
            $result['system_category_id'] = $out['system_category_id'];
        }
        return $result;
    }
}
