<?php

namespace App\Services;

use App\Models\WorkTaskInstance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dynamic focus budget: học từ actual_duration (phút tập trung thực tế) 7 ngày gần nhất.
 * budget = min(avg_focus_last_7_days * multiplier, cap).
 */
class FocusBudgetService
{
    /**
     * Trả về số phút "focus budget" cho hôm nay: dynamic từ lịch sử hoặc config mặc định.
     */
    public function getBudgetMinutes(?int $userId): int
    {
        $default = (int) config('behavior_intelligence.execution_intelligence.focus_planning.default_available_minutes', 120);
        if (! $userId || ! config('behavior_intelligence.execution_intelligence.focus_planning.dynamic_budget.enabled', true)) {
            return $default;
        }
        $cfg = config('behavior_intelligence.execution_intelligence.focus_planning.dynamic_budget');
        $windowDays = (int) ($cfg['window_days'] ?? 7);
        $multiplier = (float) ($cfg['multiplier'] ?? 0.7);
        $cap = (int) ($cfg['cap_minutes'] ?? 240);
        $min = (int) ($cfg['min_minutes'] ?? 30);

        $todayHcm = Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d');
        $from = Carbon::now('Asia/Ho_Chi_Minh')->subDays($windowDays)->format('Y-m-d');

        $total = (int) WorkTaskInstance::query()
            ->whereHas('task', fn ($q) => $q->where('user_id', $userId))
            ->where('status', WorkTaskInstance::STATUS_COMPLETED)
            ->whereBetween('instance_date', [$from, $todayHcm])
            ->whereNotNull('actual_duration')
            ->where('actual_duration', '>=', 1)
            ->sum(DB::raw('COALESCE(actual_duration, 0)'));

        $avg = $windowDays > 0 ? $total / $windowDays : 0;
        if ($avg < 1) {
            return $default;
        }
        $budget = (int) floor($avg * $multiplier);
        $budget = max($min, min($cap, $budget));
        return $budget;
    }
}
