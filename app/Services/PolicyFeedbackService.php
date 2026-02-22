<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PolicyFeedbackService
{
    /**
     * Áp dụng phản hồi đề xuất policy: cập nhật trust và ghi nhận cho policy weighting.
     * payload: ['type' => 'accepted'|'ignored'|'rejected', 'mode' => 'micro_goal'|'reduced_reminder'?]
     */
    public function apply(int $userId, array $payload): void
    {
        $type = $payload['type'] ?? null;
        $mode = $payload['mode'] ?? null;
        if (! in_array($type, ['accepted', 'ignored', 'rejected'], true)) {
            return;
        }

        $trust = app(AdaptiveTrustGradientService::class);
        $extra = $trust->getLatestVarianceAndRecovery($userId);

        if ($type === 'accepted') {
            $trust->update($userId, 0.85, 0.85, $extra['variance_score'], $extra['recovery_days']);
        } elseif ($type === 'ignored') {
            $trust->update($userId, 0.5, 0.45, $extra['variance_score'], $extra['recovery_days']);
        } else {
            $trust->update($userId, 0.4, null, $extra['variance_score'], $extra['recovery_days']);
        }

        $this->recordFeedback($userId, $type, $mode);
    }

    protected function recordFeedback(int $userId, string $type, ?string $mode): void
    {
        try {
            if (! Schema::hasTable('behavior_policy_feedback')) {
                return;
            }
            DB::table('behavior_policy_feedback')->insert([
                'user_id' => $userId,
                'feedback_type' => $type,
                'mode' => $mode,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Bảng có thể chưa có
        }
    }
}
