<?php

namespace App\Services;

use App\Models\CongViecTask;
use App\Models\WorkTaskInstance;

/**
 * Tránh học sai duration: không dùng complete_time - focus_start.
 * Idle → last_activity - start; soft cap 3× estimated; sanity nếu > 2× estimated.
 */
class FocusDurationGuardService
{
    public function idleSeconds(): int
    {
        return (int) config('behavior_intelligence.focus_duration.idle_seconds', 300);
    }

    /**
     * @return array{
     *   minutes: int|null,
     *   use_for_learning: bool,
     *   need_short_pick: bool,
     *   need_sanity_pick: bool,
     *   options: array<int>,
     *   raw_elapsed_minutes: int
     * }
     */
    public function resolveForComplete(
        WorkTaskInstance $instance,
        CongViecTask $task,
        int $startedAtUnix,
        ?int $lastActivityAtUnix,
        ?int $stoppedAtUnix
    ): array {
        $now = time();
        $idleSec = $this->idleSeconds();
        $lastAct = $lastActivityAtUnix ?? $startedAtUnix;
        // Idle: không hoạt động quá lâu → coi session kết thúc tại last_activity
        if ($now - $lastAct > $idleSec) {
            $endUnix = $lastAct;
        } elseif ($stoppedAtUnix !== null) {
            $endUnix = $stoppedAtUnix;
        } else {
            $endUnix = $now;
        }
        $elapsedSec = max(0, $endUnix - $startedAtUnix);
        $rawMinutes = max(0, (int) round($elapsedSec / 60));

        // Đã có phút đã ghi khi stop (idle/manual) — ưu tiên
        if ($instance->focus_recorded_minutes !== null && (int) $instance->focus_recorded_minutes > 0) {
            $m = (int) $instance->focus_recorded_minutes;

            return [
                'minutes' => max(1, $m),
                'use_for_learning' => true,
                'need_short_pick' => false,
                'need_sanity_pick' => false,
                'options' => [],
                'raw_elapsed_minutes' => $rawMinutes,
            ];
        }

        $estimated = $task->estimated_duration !== null ? (int) $task->estimated_duration : null;
        $cap = $estimated !== null && $estimated > 0 ? max(1, $estimated * 3) : null;
        $minutes = $rawMinutes;
        if ($cap !== null && $minutes > $cap) {
            $minutes = $cap;
        }
        if ($minutes < 1) {
            $minutes = 1;
        }

        // Rất ngắn sau start — có thể làm xong trước khi bấm start
        if ($rawMinutes < 2) {
            $opts = array_values(array_unique(array_filter([
                $estimated,
                5, 10, 15, $rawMinutes > 0 ? $rawMinutes : null,
            ], fn ($v) => $v !== null && $v >= 1)));

            return [
                'minutes' => null,
                'use_for_learning' => false,
                'need_short_pick' => true,
                'need_sanity_pick' => false,
                'options' => array_slice($opts, 0, 6),
                'raw_elapsed_minutes' => $rawMinutes,
            ];
        }

        // Quá dài so với ước lượng — hỏi user chọn
        if ($estimated !== null && $estimated > 0 && $rawMinutes > $estimated * 2) {
            $opts = [
                $estimated,
                (int) round($estimated * 1.5),
                $estimated * 2,
                min($cap ?? $rawMinutes, $rawMinutes),
            ];
            $opts = array_values(array_unique(array_filter($opts, fn ($v) => $v >= 1)));
            sort($opts);

            return [
                'minutes' => null,
                'use_for_learning' => false,
                'need_short_pick' => false,
                'need_sanity_pick' => true,
                'options' => array_slice($opts, 0, 6),
                'raw_elapsed_minutes' => $rawMinutes,
            ];
        }

        return [
            'minutes' => max(1, $minutes),
            'use_for_learning' => true,
            'need_short_pick' => false,
            'need_sanity_pick' => false,
            'options' => [],
            'raw_elapsed_minutes' => $rawMinutes,
        ];
    }
}
