<?php

namespace App\Services;

use App\Models\CongViecTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RecoveryIntelligenceService
{
    protected int $stableStreakDays;
    protected int $slowRecoveryDays;

    public function __construct()
    {
        $cfg = config('behavior_intelligence.recovery', []);
        $this->stableStreakDays = (int) ($cfg['stable_streak_days'] ?? 3);
        $this->slowRecoveryDays = (int) ($cfg['slow_recovery_days'] ?? 5);
    }

    /**
     * Cập nhật recovery state: xác định fail, recovery days, streak sau hồi phục.
     */
    public function computeAndStore(int $userId, string $upToDate): array
    {
        if (! config('behavior_intelligence.layers.recovery_intelligence', true)) {
            return [];
        }

        $upTo = Carbon::parse($upToDate)->startOfDay();
        $from = $upTo->copy()->subDays(90);

        $expectedDays = $this->getExpectedActivityDays($userId, $from->format('Y-m-d'), $upTo->format('Y-m-d'));
        $completedDays = $this->getCompletedDays($userId, $from->format('Y-m-d'), $upTo->format('Y-m-d'));
        $lastFailAt = null;
        $recoveryDays = null;
        $streakAfterRecovery = 0;

        $sortedExpected = array_unique($expectedDays);
        sort($sortedExpected);
        $lastFail = null;
        $currentStreak = 0;
        foreach ($sortedExpected as $d) {
            if (in_array($d, $completedDays, true)) {
                if ($lastFail !== null) {
                    $recoveryDays = (Carbon::parse($lastFail)->diffInDays(Carbon::parse($d)));
                    $lastFail = null;
                    $currentStreak = 1;
                } else {
                    $currentStreak++;
                }
            } else {
                $lastFail = $d;
                $lastFailAt = $lastFail;
                if ($currentStreak >= $this->stableStreakDays) {
                    $streakAfterRecovery = $currentStreak;
                }
                $currentStreak = 0;
            }
        }
        if ($lastFail !== null) {
            $lastFailAt = $lastFail;
        }
        if ($currentStreak >= $this->stableStreakDays) {
            $streakAfterRecovery = $currentStreak;
        }

        DB::table('behavior_recovery_state')->updateOrInsert(
            ['user_id' => $userId],
            [
                'last_fail_at' => $lastFailAt,
                'recovery_days' => $recoveryDays,
                'streak_after_recovery' => $streakAfterRecovery,
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );

        return [
            'last_fail_at' => $lastFailAt,
            'recovery_days' => $recoveryDays,
            'streak_after_recovery' => $streakAfterRecovery,
        ];
    }

    /**
     * Ngày có kỳ vọng làm (due hoặc repeat rơi vào ngày đó).
     *
     * @return array<int, string> list of Y-m-d
     */
    protected function getExpectedActivityDays(int $userId, string $from, string $to): array
    {
        $tasks = CongViecTask::where('user_id', $userId)
            ->whereNotNull('due_date')
            ->where('due_date', '>=', $from)
            ->where('due_date', '<=', $to)
            ->get();
        $days = [];
        foreach ($tasks as $t) {
            $current = Carbon::parse($t->due_date);
            $end = Carbon::parse($to);
            while ($current->lte($end)) {
                $d = $current->format('Y-m-d');
                if ($current->gte($from)) {
                    if ($t->occursOn($d)) {
                        $days[] = $d;
                    }
                }
                $current->addDay();
                if (($t->repeat ?? 'none') === 'none') {
                    break;
                }
                if ($t->repeat_until && $current->gt(Carbon::parse($t->repeat_until))) {
                    break;
                }
            }
        }

        return $days;
    }

    /**
     * Ngày có ít nhất một task completed (theo updated_at).
     *
     * @return array<int, string> list of Y-m-d
     */
    protected function getCompletedDays(int $userId, string $from, string $to): array
    {
        $dates = CongViecTask::where('user_id', $userId)
            ->where('completed', true)
            ->whereBetween('updated_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->pluck('updated_at')
            ->map(fn ($t) => Carbon::parse($t)->format('Y-m-d'))
            ->unique()
            ->values()
            ->all();

        return $dates;
    }

    public function isSlowRecovery(int $userId): bool
    {
        $row = DB::table('behavior_recovery_state')->where('user_id', $userId)->first();

        return $row && $row->recovery_days !== null && $row->recovery_days > $this->slowRecoveryDays;
    }
}
