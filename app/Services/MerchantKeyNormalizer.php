<?php

namespace App\Services;

use App\Models\MerchantGroupPattern;
use Illuminate\Support\Facades\Cache;

class MerchantKeyNormalizer
{
    private const MAX_LENGTH = 255;

    private const MIN_MERCHANT_LENGTH = 2;

    private const PATTERNS_CACHE_KEY = 'merchant_group_patterns_ordered';

    private const PATTERNS_CACHE_TTL = 3600;

    /**
     * Trả về cả merchant_key và merchant_group. Pattern đọc từ DB (merchant_group_patterns), có cache.
     * Không match → fallback theo từ (key + group từ từ đầu).
     */
    public function normalizeWithGroup(?string $description): array
    {
        if ($description === null || trim($description) === '') {
            return ['merchant_key' => 'unknown', 'merchant_group' => 'unknown'];
        }

        $d = trim($description);
        $matched = $this->matchFromPatterns($d);
        if ($matched !== null) {
            return $matched;
        }

        $key = $this->normalizeFromWords($description);
        $group = $key;
        $firstSpace = strpos($key, ' ');
        if ($firstSpace !== false) {
            $group = substr($key, 0, $firstSpace);
        }
        if (mb_strlen($group) < self::MIN_MERCHANT_LENGTH) {
            $group = $key;
        }
        return ['merchant_key' => $key, 'merchant_group' => $group];
    }

    /**
     * Tạo stable_merchant_identifier từ description (backward compat; classifier nên dùng normalizeWithGroup).
     */
    public function normalize(?string $description): string
    {
        $pair = $this->normalizeWithGroup($description);
        return $pair['merchant_key'];
    }

    /**
     * Chuẩn hóa merchant_key để so sánh na ná (bỏ chuỗi số trong từng từ).
     * Dùng khi match rule: rule lưu "pay985091starter gd" vẫn match giao dịch có key "paystarter gd".
     */
    public function normalizeKeyForMatch(string $merchantKey): string
    {
        if ($merchantKey === '' || $merchantKey === 'unknown') {
            return $merchantKey;
        }
        $words = array_filter(explode(' ', $merchantKey), fn ($w) => $w !== '');
        $normalized = array_map(fn ($w) => $this->normalizeWordSimilar($w), $words);
        $key = implode(' ', array_filter($normalized));
        return $key !== '' ? $key : $merchantKey;
    }

    /** Độ dài prefix từ đầu để coi cùng cụm (PAY...STARTER và PAY...BASIC cùng cụm "pay"). */
    private const KEY_PATTERN_PREFIX_LEN = 3;

    /**
     * Pattern theo cụm chung: prefix của từ đầu + từ cuối (nếu có 2+ từ).
     * PAY...STARTER GD và PAY...BASIC GD → cùng pattern "pay gd", ưu tiên match với nhau.
     */
    public function keyPatternForMatch(string $merchantKey): string
    {
        if ($merchantKey === '' || $merchantKey === 'unknown') {
            return $merchantKey;
        }
        $normalized = $this->normalizeKeyForMatch($merchantKey);
        $words = array_values(array_filter(explode(' ', $normalized), fn ($w) => $w !== ''));
        if (count($words) === 0) {
            return $normalized;
        }
        $firstPrefix = mb_substr($words[0], 0, self::KEY_PATTERN_PREFIX_LEN);
        if (count($words) === 1) {
            return $firstPrefix;
        }
        return $firstPrefix . ' ' . $words[count($words) - 1];
    }

    /**
     * Match mô tả với bảng merchant_group_patterns (ưu tiên theo priority). Trả về ['merchant_key','merchant_group'] hoặc null.
     */
    private function matchFromPatterns(string $description): ?array
    {
        $patterns = $this->getOrderedPatterns();
        foreach ($patterns as $p) {
            if ($p->matches($description)) {
                return [
                    'merchant_key' => $p->merchant_key,
                    'merchant_group' => $p->merchant_group,
                ];
            }
        }
        return null;
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, MerchantGroupPattern> */
    private function getOrderedPatterns()
    {
        return Cache::remember(self::PATTERNS_CACHE_KEY, self::PATTERNS_CACHE_TTL, function () {
            return MerchantGroupPattern::where('is_active', true)
                ->orderByDesc('priority')
                ->get();
        });
    }

    public static function clearPatternCache(): void
    {
        Cache::forget(self::PATTERNS_CACHE_KEY);
    }

    private function normalizeFromWords(string $description): string
    {
        $text = $this->stripDynamicParts($description);
        $text = mb_strtolower($text, 'UTF-8');
        $text = $this->removeVietnameseTone($text);
        $text = preg_replace('/[^a-z0-9\s]/u', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        $words = array_values(array_filter(explode(' ', $text), fn ($w) => $w !== ''));
        if (count($words) === 0) {
            return 'unknown';
        }

        $kept = [];
        foreach ($words as $i => $w) {
            if ($i === 0) {
                $kept[] = $this->normalizeWordSimilar($w);
                continue;
            }
            if ($this->looksLikeRef($w)) {
                break;
            }
            $kept[] = $this->normalizeWordSimilar($w);
        }
        $key = implode(' ', $kept);
        $key = trim($key);
        if ($key === '' || mb_strlen($key) < self::MIN_MERCHANT_LENGTH) {
            return 'unknown';
        }
        if (mb_strlen($key) > self::MAX_LENGTH) {
            $key = mb_substr($key, 0, self::MAX_LENGTH);
        }
        return $key;
    }

    /** Loại bỏ phần động: chuỗi số dài, pattern ngày, pattern giờ. */
    private function stripDynamicParts(string $text): string
    {
        $text = (string) preg_replace('/\b\d{1,2}:\d{2}(:\d{2})?\b/u', ' ', $text);
        $text = (string) preg_replace('/\b\d{6,}\b/u', ' ', $text);
        $text = (string) preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }

    /**
     * Chuẩn hóa từ để nội dung na ná cho cùng merchant_key (chỉ bỏ chữ số trong từ, giữ nguyên chữ).
     * VD: pay137911starter → paystarter, PAY985091STARTER → paystarter.
     * PAY131700BASIC → paybasic (khác paystarter, nên không khớp với rule STARTER).
     */
    private function normalizeWordSimilar(string $word): string
    {
        $out = (string) preg_replace('/\d+/u', '', $word);
        return $out !== '' ? $out : $word;
    }

    /**
     * Từ thứ hai trông như mã ref (số dài, hoặc alphanumeric dài) thì chỉ giữ từ đầu.
     */
    private function looksLikeRef(string $word): bool
    {
        if (preg_match('/^\d{5,}$/u', $word)) {
            return true;
        }
        if (mb_strlen($word) >= 5 && preg_match('/^[a-z0-9]+$/u', $word)) {
            return true;
        }
        return false;
    }

    private function removeVietnameseTone(string $str): string
    {
        $vietnamese = [
            'à', 'á', 'ạ', 'ả', 'ã', 'â', 'ầ', 'ấ', 'ậ', 'ẩ', 'ẫ', 'ă', 'ằ', 'ắ', 'ặ', 'ẳ', 'ẵ',
            'è', 'é', 'ẹ', 'ẻ', 'ẽ', 'ê', 'ề', 'ế', 'ệ', 'ể', 'ễ',
            'ì', 'í', 'ị', 'ỉ', 'ĩ',
            'ò', 'ó', 'ọ', 'ỏ', 'õ', 'ô', 'ồ', 'ố', 'ộ', 'ổ', 'ỗ', 'ơ', 'ờ', 'ớ', 'ợ', 'ở', 'ỡ',
            'ù', 'ú', 'ụ', 'ủ', 'ũ', 'ư', 'ừ', 'ứ', 'ự', 'ử', 'ữ',
            'ỳ', 'ý', 'ỵ', 'ỷ', 'ỹ',
            'đ',
        ];
        $ascii = [
            'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a',
            'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e',
            'i', 'i', 'i', 'i', 'i',
            'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o',
            'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u',
            'y', 'y', 'y', 'y', 'y',
            'd',
        ];
        return str_replace($vietnamese, $ascii, $str);
    }
}
