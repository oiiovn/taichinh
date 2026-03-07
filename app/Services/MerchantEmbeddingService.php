<?php

namespace App\Services;

use App\Models\MerchantEmbedding;
use App\Models\GlobalMerchantPattern;
use App\Models\UserMerchantRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MerchantEmbeddingService
{
    public function __construct(
        private MerchantKeyNormalizer $normalizer
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('merchant_embedding.enabled', true);
    }

    /**
     * Embed text (description hoặc merchant_key) thành vector.
     *
     * @return array<float>
     */
    public function embed(string $text): array
    {
        $driver = config('merchant_embedding.driver', 'local');
        $dim = (int) config('merchant_embedding.dimension', 64);

        if ($driver === 'openai') {
            return $this->embedOpenAi($text, $dim);
        }

        return $this->embedLocal($text, $dim);
    }

    /**
     * Lấy hoặc tạo embedding cho merchant_key; lưu vào merchant_embeddings.
     *
     * @return array<float>
     */
    public function getOrCreateForMerchantKey(string $merchantKey): array
    {
        if ($merchantKey === '' || $merchantKey === 'unknown') {
            return $this->embed($merchantKey);
        }

        $row = MerchantEmbedding::where('merchant_key', $merchantKey)->first();
        if ($row !== null && is_array($row->vector) && count($row->vector) > 0) {
            return $row->vector;
        }

        $vector = $this->embed($merchantKey);
        MerchantEmbedding::updateOrCreate(
            ['merchant_key' => $merchantKey],
            ['vector' => $vector, 'dimension' => count($vector)]
        );
        return $vector;
    }

    /**
     * Tìm các merchant_key có vector gần nhất với $vector (cosine similarity).
     *
     * @param  array<float>  $vector
     * @return array<int, array{merchant_key: string, score: float}>
     */
    public function findNearest(array $vector, int $limit = 5, float $minScore = 0.0): array
    {
        $dim = count($vector);
        if ($dim === 0) {
            return [];
        }

        $all = MerchantEmbedding::where('dimension', $dim)->get();
        if ($all->isEmpty()) {
            return [];
        }

        $scores = [];
        foreach ($all as $row) {
            $v = $row->vector;
            if (! is_array($v) || count($v) !== $dim) {
                continue;
            }
            $score = $this->cosineSimilarity($vector, $v);
            if ($score >= $minScore) {
                $scores[] = ['merchant_key' => $row->merchant_key, 'score' => $score];
            }
        }

        usort($scores, fn ($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($scores, 0, $limit);
    }

    /**
     * Cosine similarity giữa hai vector (0..1 nếu đã chuẩn hóa dương).
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        if ($n === 0) {
            return 0.0;
        }
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $x = (float) $a[$i];
            $y = (float) $b[$i];
            $dot += $x * $y;
            $normA += $x * $x;
            $normB += $y * $y;
        }
        if ($normA <= 0 || $normB <= 0) {
            return 0.0;
        }
        $sim = $dot / (sqrt($normA) * sqrt($normB));
        return (float) max(-1.0, min(1.0, $sim));
    }

    /**
     * Local embedding: n-gram + hash projection → vector, L2 normalize.
     * Cùng ngữ nghĩa gần (Highlands, Starbucks) có n-gram overlap nên vector gần nhau.
     *
     * @return array<float>
     */
    private function embedLocal(string $text, int $dimension): array
    {
        $text = $this->normalizeForEmbedding($text);
        if ($text === '') {
            return $this->zeroVector($dimension);
        }

        $cfg = config('merchant_embedding.local', []);
        $ngramSize = (int) ($cfg['ngram_size'] ?? 2);
        $ngrams = $this->ngrams($text, $ngramSize);

        $vector = array_fill(0, $dimension, 0.0);
        foreach ($ngrams as $ng) {
            $idx = abs(crc32($ng)) % $dimension;
            $vector[$idx] += 1.0;
        }

        return $this->l2Normalize($vector);
    }

    private function normalizeForEmbedding(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = preg_replace('/[^a-z0-9\s]/u', ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s);
        return trim($s);
    }

    /**
     * @return array<string>
     */
    private function ngrams(string $text, int $n): array
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $out = [];
        for ($i = 0; $i <= count($chars) - $n; $i++) {
            $out[] = implode('', array_slice($chars, $i, $n));
        }
        return $out;
    }

    private function l2Normalize(array $v): array
    {
        $norm = 0.0;
        foreach ($v as $x) {
            $norm += $x * $x;
        }
        if ($norm <= 0) {
            return $v;
        }
        $scale = 1.0 / sqrt($norm);
        return array_map(fn ($x) => (float) ($x * $scale), $v);
    }

    private function zeroVector(int $dim): array
    {
        $v = array_fill(0, $dim, 0.0);
        $v[0] = 1.0;
        return $v;
    }

    /**
     * OpenAI Embeddings API.
     *
     * @return array<float>
     */
    private function embedOpenAi(string $text, int $dimension): array
    {
        $apiKey = config('merchant_embedding.openai.api_key', '');
        $model = config('merchant_embedding.openai.model', 'text-embedding-3-small');
        if ($apiKey === '') {
            Log::warning('MerchantEmbeddingService: OPENAI_API_KEY empty, fallback to local');
            return $this->embedLocal($text, $dimension);
        }

        $override = config('merchant_embedding.openai.dimension_override');
        $requestDim = $override ?? $dimension;

        try {
            $response = Http::withToken($apiKey)
                ->timeout(10)
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => $model,
                    'input' => mb_substr($text, 0, 8000),
                    'dimensions' => $requestDim,
                ]);

            if (! $response->successful()) {
                Log::warning('MerchantEmbeddingService OpenAI error: ' . $response->body());
                return $this->embedLocal($text, $dimension);
            }

            $data = $response->json();
            $vec = $data['data'][0]['embedding'] ?? null;
            if (! is_array($vec)) {
                return $this->embedLocal($text, $dimension);
            }

            $vec = array_map('floatval', $vec);
            if (count($vec) !== $dimension && count($vec) > 0 && $dimension > 0) {
                $vec = $this->resizeVector($vec, $dimension);
            }
            return $this->l2Normalize($vec);
        } catch (\Throwable $e) {
            Log::warning('MerchantEmbeddingService OpenAI exception: ' . $e->getMessage());
            return $this->embedLocal($text, $dimension);
        }
    }

    private function resizeVector(array $v, int $targetDim): array
    {
        $n = count($v);
        if ($n === $targetDim) {
            return $v;
        }
        if ($n < $targetDim) {
            return array_merge($v, array_fill(0, $targetDim - $n, 0.0));
        }
        return array_slice($v, 0, $targetDim);
    }

    /**
     * Trả về category từ rule/global cho danh sách merchant_key gần nhất (ưu tiên rule user, rồi global).
     * Dùng trong CandidateCollector để build embedding candidate.
     *
     * @param  array<int, array{merchant_key: string, score: float}>  $nearest
     * @return array{user_category_id: int|null, system_category_id: int|null, score: float, source: string}|null
     */
    public function resolveCategoryFromNearest(int $userId, string $direction, array $nearest): ?array
    {
        foreach ($nearest as $item) {
            $key = $item['merchant_key'];
            $score = $item['score'];

            $rule = UserMerchantRule::where('user_id', $userId)->where('merchant_key', $key)->first();
            if ($rule !== null) {
                return [
                    'user_category_id' => $rule->mapped_user_category_id,
                    'system_category_id' => null,
                    'score' => $score,
                    'source' => 'embedding_rule',
                ];
            }

            $group = $this->normalizer->normalizeWithGroup($key)['merchant_group'] ?? $key;
            $pattern = GlobalMerchantPattern::where('merchant_group', $group)
                ->where(function ($q) use ($direction) {
                    $q->where('direction', $direction)->orWhere('direction', '');
                })
                ->orderByRaw('CASE WHEN direction = ? THEN 0 ELSE 1 END', [$direction])
                ->orderByDesc('usage_count')
                ->first();
            if ($pattern !== null && $pattern->usage_count > 0) {
                $conf = $pattern->getConfidenceFromAccuracy();
                if ($conf >= 0.4) {
                    return [
                        'user_category_id' => null,
                        'system_category_id' => $pattern->system_category_id,
                        'score' => $score * $conf,
                        'source' => 'embedding_global',
                    ];
                }
            }
        }
        return null;
    }
}
