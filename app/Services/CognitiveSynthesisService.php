<?php

namespace App\Services;

use App\Models\FinancialInsightAiCache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Lớp Cognitive Layer: nhận narrative từ NarrativeBuilder (engine), gọi GPT chỉ để diễn giải
 * thành chiến lược mạch lạc. Không tính lại state/risk. Fallback về narrative engine khi lỗi.
 */
class CognitiveSynthesisService
{
    public function synthesize(
        int $userId,
        array $narrativeResult,
        array $insightPayload,
        float $narrativeConfidence,
        bool $forceRefresh = false
    ): ?string {
        $config = config('financial_brain.cognitive_layer', []);
        if (! ($config['use_cognitive_layer'] ?? false)) {
            return null;
        }

        $threshold = (float) ($config['confidence_threshold'] ?? 0.6);
        if ($narrativeConfidence < $threshold) {
            return null;
        }

        $snapshotHash = $this->snapshotHash($narrativeResult, $insightPayload);
        if (! $forceRefresh) {
            $cached = $this->getFromCache($userId, $snapshotHash);
            if ($cached !== null) {
                return $cached;
            }
        }

        $apiKey = config('classification.gpt.api_key');
        if (empty($apiKey)) {
            return null;
        }

        $userPrompt = InsightPayloadService::buildUserPrompt($insightPayload);
        $systemPrompt = InsightPayloadService::GPT_SYSTEM_PROMPT;

        $timeout = (int) ($config['timeout_seconds'] ?? 20);

        try {
            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('classification.gpt.model', 'gpt-4o-mini'),
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'temperature' => 0.3,
                ]);

            if (! $response->successful()) {
                Log::warning('CognitiveSynthesis GPT API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $body = $response->json();
            $content = trim((string) ($body['choices'][0]['message']['content'] ?? ''));
            if ($content === '') {
                return null;
            }

            $this->putCache($userId, $snapshotHash, $content);
            return $content;
        } catch (\Throwable $e) {
            Log::warning('CognitiveSynthesis exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    public function snapshotHash(array $narrativeResult, array $insightPayload): string
    {
        $cognitive = $insightPayload['cognitive_input'] ?? [];
        $drift = $cognitive['drift_signals'] ?? [];
        $payload = [
            'structural_key' => $cognitive['structural_state']['key'] ?? '',
            'weakest_pillar' => $cognitive['weakest_pillar'] ?? '',
            'trajectory' => $cognitive['trajectory']['direction'] ?? 'stable',
            'risk_level' => $insightPayload['snapshot']['risk_level'] ?? '',
            'narrative_confidence' => (int) (($narrativeResult['narrative_confidence'] ?? 0) * 10),
            'drift_dsi_trend' => $drift['dsi_trend'] ?? 'stable',
            'drift_summary' => $drift['summary'] ?? '',
            'drift_repeated_high_dsi' => (bool) ($drift['repeated_high_dsi'] ?? false),
        ];
        return hash('sha256', json_encode($payload));
    }

    private function getFromCache(int $userId, string $snapshotHash): ?string
    {
        $ttlHours = (int) config('financial_brain.cognitive_layer.cache_ttl_hours', 24);
        $row = FinancialInsightAiCache::where('user_id', $userId)
            ->where('snapshot_hash', $snapshotHash)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();
        return $row ? $row->narrative : null;
    }

    private function putCache(int $userId, string $snapshotHash, string $narrative): void
    {
        $ttlHours = (int) config('financial_brain.cognitive_layer.cache_ttl_hours', 24);
        $expiresAt = $ttlHours > 0 ? now()->addHours($ttlHours) : null;
        FinancialInsightAiCache::updateOrCreate(
            ['user_id' => $userId, 'snapshot_hash' => $snapshotHash],
            ['narrative' => $narrative, 'expires_at' => $expiresAt]
        );
    }
}
