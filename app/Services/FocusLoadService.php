<?php

namespace App\Services;

use App\Models\WorkTaskInstance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Focus Load = tổng phút tập trung liên tục (từ completions sau lần nghỉ gần nhất).
 * Dùng để gợi ý nghỉ nhẹ, không ép Pomodoro.
 */
class FocusLoadService
{
    public const SESSION_LAST_BREAK_KEY = 'focus_last_break_at';

    public function getLastBreakAt(?int $userId): ?Carbon
    {
        if (! $userId) {
            return null;
        }
        $v = session(self::SESSION_LAST_BREAK_KEY);
        if (! $v) {
            return null;
        }
        if (is_array($v) && ($v['user_id'] ?? null) !== $userId) {
            return null;
        }
        $at = is_array($v) ? ($v['at'] ?? null) : $v;
        return $at ? Carbon::parse($at, 'Asia/Ho_Chi_Minh') : null;
    }

    /** Ghi nhận user bấm "Nghỉ" → reset FL từ thời điểm này. */
    public function recordBreak(int $userId): void
    {
        session([
            self::SESSION_LAST_BREAK_KEY => [
                'user_id' => $userId,
                'at' => now('Asia/Ho_Chi_Minh')->toIso8601String(),
            ],
        ]);
    }

    /**
     * Tổng phút đã tập trung (actual_duration) từ sau last_break (hoặc đầu ngày) đến giờ.
     * Chỉ tính instance đã complete có actual_duration.
     */
    public function getFocusLoadMinutes(?int $userId): int
    {
        if (! $userId) {
            return 0;
        }
        $since = $this->getLastBreakAt($userId);
        $todayStart = Carbon::now('Asia/Ho_Chi_Minh')->startOfDay();
        $from = $since && $since->gt($todayStart) ? $since : $todayStart;

        $sum = WorkTaskInstance::query()
            ->whereHas('task', fn ($q) => $q->where('user_id', $userId))
            ->where('status', WorkTaskInstance::STATUS_COMPLETED)
            ->whereNotNull('actual_duration')
            ->where('actual_duration', '>=', 1)
            ->where('completed_at', '>=', $from)
            ->sum(DB::raw('COALESCE(actual_duration, 0)'));

        return (int) min(999, $sum);
    }

    /**
     * Sau khi user vừa complete — có nên gợi ý nghỉ không.
     * Không ép: chỉ gợi ý khi FL >= threshold_short (45) hoặc threshold_long (90).
     *
     * @return array{show: true, focus_load: int, short_break: bool, break_minutes: int, message: string}|null
     */
    public function maybeSuggestBreak(?int $userId): ?array
    {
        if (! $userId || ! config('behavior_intelligence.enabled', true)) {
            return null;
        }
        $thresholdShort = (int) config('behavior_intelligence.break_suggestion.threshold_short_minutes', 45);
        $thresholdLong = (int) config('behavior_intelligence.break_suggestion.threshold_long_minutes', 90);
        $breakShort = (int) config('behavior_intelligence.break_suggestion.break_duration_short', 5);
        $breakLong = (int) config('behavior_intelligence.break_suggestion.break_duration_long', 10);

        $fl = $this->getFocusLoadMinutes($userId);
        if ($fl < $thresholdShort) {
            return null;
        }

        $long = $fl >= $thresholdLong;
        $breakMinutes = $long ? $breakLong : $breakShort;
        $message = $long
            ? sprintf('Bạn đã tập trung %d phút. Nghỉ %d–15 phút sẽ giúp duy trì hiệu suất.', $fl, $breakLong)
            : sprintf('Bạn đã tập trung %d phút. Nghỉ 3–%d phút sẽ giúp duy trì hiệu suất.', $fl, $breakShort);

        return [
            'show' => true,
            'focus_load' => $fl,
            'short_break' => ! $long,
            'break_minutes' => $breakMinutes,
            'message' => $message,
        ];
    }
}
