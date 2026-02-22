<?php

namespace App\Services;

use App\Models\CongViecTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BehaviorIndicatorsService
{
    /**
     * Discipline Elasticity: khả năng co giãn khi áp lực (recovery nhanh + nhiều task = tốt).
     */
    public function disciplineElasticity(int $userId): float
    {
        $recovery = DB::table('behavior_recovery_state')->where('user_id', $userId)->first();
        $recoveryDays = $recovery ? (int) $recovery->recovery_days : null;
        $activeCount = CongViecTask::where('user_id', $userId)->where('completed', false)->count();
        $base = 0.5;
        if ($recoveryDays !== null && $recoveryDays <= 2) {
            $base += 0.3;
        } elseif ($recoveryDays !== null && $recoveryDays <= 5) {
            $base += 0.1;
        }
        if ($activeCount >= 5) {
            $base += 0.1;
        }

        return min(1.0, round($base, 4));
    }

    /**
     * Motivation Volatility: độ lệch chuẩn completion rate theo tuần.
     */
    public function motivationVolatility(int $userId, int $weeks = 12): ?float
    {
        $end = Carbon::now()->endOfWeek();
        $rates = [];
        for ($i = 0; $i < $weeks; $i++) {
            $start = $end->copy()->startOfWeek()->subWeeks($i);
            $weekEnd = $start->copy()->endOfWeek();
            $completed = CongViecTask::where('user_id', $userId)
                ->where('completed', true)
                ->whereBetween('updated_at', [$start, $weekEnd])
                ->count();
            $due = CongViecTask::where('user_id', $userId)
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [$start->format('Y-m-d'), $weekEnd->format('Y-m-d')])
                ->count();
            $rates[] = $due > 0 ? $completed / $due : 0;
        }
        if (count($rates) < 2) {
            return null;
        }
        $mean = array_sum($rates) / count($rates);
        $variance = array_sum(array_map(fn ($x) => ($x - $mean) ** 2, $rates)) / (count($rates) - 1);

        return round((float) sqrt($variance), 4);
    }

    /**
     * Self-Regulation Strength: từ recovery nhanh (recovery_days thấp).
     */
    public function selfRegulationStrength(int $userId): float
    {
        $recovery = DB::table('behavior_recovery_state')->where('user_id', $userId)->first();
        if (! $recovery || $recovery->recovery_days === null) {
            return 0.5;
        }
        $days = (int) $recovery->recovery_days;
        if ($days <= 1) {
            return 0.9;
        }
        if ($days <= 3) {
            return 0.7;
        }
        if ($days <= 5) {
            return 0.5;
        }

        return max(0.2, 0.5 - ($days - 5) * 0.05);
    }
}
