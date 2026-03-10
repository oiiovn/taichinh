<?php

namespace App\Services;

/**
 * Phiên focus: elapsed dựa trên last_activity (idle) — không dùng complete_time - start.
 */
class FocusSessionService
{
    public const SESSION_KEY = 'focus_session';

    public function get(?int $userId): ?array
    {
        if (! $userId) {
            return null;
        }
        $data = session(self::SESSION_KEY);
        if (! is_array($data) || empty($data['instance_id']) || empty($data['started_at'])) {
            return null;
        }
        if (($data['user_id'] ?? null) !== $userId) {
            return null;
        }

        return $data;
    }

    public function start(int $userId, int $instanceId): void
    {
        $t = time();
        session([
            self::SESSION_KEY => [
                'user_id' => $userId,
                'instance_id' => $instanceId,
                'started_at' => $t,
                'last_activity_at' => $t,
            ],
        ]);
    }

    /** Cập nhật hoạt động (click/scroll/keypress/visibility) — chống idle. */
    public function touchActivity(int $userId): void
    {
        $s = $this->get($userId);
        if (! $s) {
            return;
        }
        $s['last_activity_at'] = time();
        session([self::SESSION_KEY => $s]);
    }

    public function stop(int $userId): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function elapsedSeconds(int $userId): int
    {
        $s = $this->get($userId);
        if (! $s) {
            return 0;
        }

        return max(0, time() - (int) $s['started_at']);
    }

    public function lastActivityAt(int $userId): ?int
    {
        $s = $this->get($userId);
        if (! $s) {
            return null;
        }

        return isset($s['last_activity_at']) ? (int) $s['last_activity_at'] : (int) $s['started_at'];
    }

    /** Phút làm tròn theo logic idle (không dùng complete_time). */
    public function elapsedMinutesRounded(int $userId): int
    {
        $s = $this->get($userId);
        if (! $s) {
            return 1;
        }
        $idleSec = (int) config('behavior_intelligence.focus_duration.idle_seconds', 300);
        $lastAct = (int) ($s['last_activity_at'] ?? $s['started_at']);
        $now = time();
        $end = ($now - $lastAct > $idleSec) ? $lastAct : $now;
        $sec = max(0, $end - (int) $s['started_at']);

        return max(1, (int) round($sec / 60));
    }
}
