<?php

namespace App\Services;

use App\Models\BehaviorEvent;
use App\Models\CongViecTask;
use Carbon\Carbon;

class ProbabilisticTruthService
{
    protected float $thresholdConfirm;
    protected float $thresholdAuto;

    public function __construct()
    {
        $cfg = config('behavior_intelligence.probabilistic_truth', []);
        $this->thresholdConfirm = (float) ($cfg['threshold_require_confirmation'] ?? 0.7);
        $this->thresholdAuto = (float) ($cfg['threshold_auto_accept'] ?? 0.9);
    }

    /**
     * Tính P(real_completion | behavior). Trả về ['p' => float, 'require_confirmation' => bool].
     * $pendingPayload: optional ['latency_ms' => int] khi tick chưa lưu, dùng cho delay penalty.
     *
     * @param  array{latency_ms?: int}|null  $pendingPayload
     * @return array{p: float, require_confirmation: bool}
     */
    public function estimate(int $userId, int $workTaskId, ?array $pendingPayload = null): array
    {
        if (! config('behavior_intelligence.layers.probabilistic_truth', true)) {
            return ['p' => 1.0, 'require_confirmation' => false];
        }

        $task = CongViecTask::where('id', $workTaskId)->where('user_id', $userId)->first();
        if (! $task) {
            return ['p' => 0.0, 'require_confirmation' => true];
        }

        $base = 0.9;
        $delayPenalty = $this->delayPenalty($userId, $workTaskId, $pendingPayload);
        $anomalyPenalty = $this->anomalyPenalty($userId, $workTaskId);

        $p = $base * (1.0 - $delayPenalty) * (1.0 - $anomalyPenalty);
        $p = max(0.0, min(1.0, $p));

        $requireConfirmation = $p < $this->thresholdConfirm;

        return ['p' => round($p, 4), 'require_confirmation' => $requireConfirmation];
    }

    protected function delayPenalty(int $userId, int $workTaskId, ?array $pendingPayload = null): float
    {
        $latencyMs = null;
        if ($pendingPayload !== null && isset($pendingPayload['latency_ms'])) {
            $latencyMs = (int) $pendingPayload['latency_ms'];
        }
        if ($latencyMs === null) {
            $tick = BehaviorEvent::where('user_id', $userId)
                ->where('work_task_id', $workTaskId)
                ->where('event_type', BehaviorEvent::TYPE_TASK_TICK_AT)
                ->orderByDesc('created_at')
                ->first();
            if (! $tick || ! is_array($tick->payload) || empty($tick->payload['latency_ms'])) {
                return 0.0;
            }
            $latencyMs = (int) $tick->payload['latency_ms'];
        }
        if ($latencyMs <= 0) {
            return 0.0;
        }
        $hoursLate = $latencyMs / (1000.0 * 3600.0);
        if ($hoursLate <= 0) {
            return 0.0;
        }
        return min(0.5, $hoursLate * 0.05);
    }

    protected function anomalyPenalty(int $userId, int $workTaskId): float
    {
        $recent = BehaviorEvent::where('user_id', $userId)
            ->where('work_task_id', $workTaskId)
            ->whereIn('event_type', [BehaviorEvent::TYPE_TASK_TICK_AT, BehaviorEvent::TYPE_DWELL_MS])
            ->where('created_at', '>=', Carbon::now()->subHours(24))
            ->count();
        if ($recent === 0) {
            return 0.1;
        }
        return 0.0;
    }
}
