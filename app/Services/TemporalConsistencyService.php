<?php

namespace App\Services;

use App\Models\BehaviorEvent;
use App\Models\CongViecTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TemporalConsistencyService
{
    /**
     * Tính variance, drift, streak risk cho user (và optional program) trong khoảng ngày.
     *
     * @return array{variance_score: float|null, drift_slope: float|null, streak_risk: string|null}
     */
    public function computeAndStore(int $userId, string $periodStart, string $periodEnd, ?int $programId = null): array
    {
        if (! config('behavior_intelligence.layers.temporal_consistency', true)) {
            return ['variance_score' => null, 'drift_slope' => null, 'streak_risk' => null];
        }

        $completionTimes = $this->getCompletionTimestamps($userId, $periodStart, $periodEnd, $programId);
        $varianceScore = $this->computeVarianceScore($completionTimes);
        $driftSlope = $this->computeDriftSlope($completionTimes);
        $streakRisk = $this->estimateStreakRisk($userId, $periodStart, $periodEnd, $driftSlope);

        $keys = [
            'user_id' => $userId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ];
        if (Schema::hasColumn('behavior_temporal_aggregates', 'program_id')) {
            $keys['program_id'] = $programId;
        }
        DB::table('behavior_temporal_aggregates')->updateOrInsert(
            $keys,
            [
                'variance_score' => $varianceScore,
                'drift_slope' => $driftSlope,
                'streak_risk' => $streakRisk,
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );

        return [
            'variance_score' => $varianceScore,
            'drift_slope' => $driftSlope,
            'streak_risk' => $streakRisk,
        ];
    }

    /**
     * Lấy danh sách timestamp hoàn thành (ưu tiên từ behavior_events task_tick_at, fallback updated_at khi completed).
     * $programId: khi set chỉ lấy task thuộc program.
     *
     * @return array<string, int> date => timestamp (start of day or actual tick time)
     */
    protected function getCompletionTimestamps(int $userId, string $periodStart, string $periodEnd, ?int $programId = null): array
    {
        $taskIds = null;
        if ($programId !== null) {
            $taskIds = CongViecTask::where('user_id', $userId)->where('program_id', $programId)->pluck('id')->all();
            if (empty($taskIds)) {
                return [];
            }
        }

        $out = [];
        $tickQuery = BehaviorEvent::where('user_id', $userId)
            ->where('event_type', BehaviorEvent::TYPE_TASK_TICK_AT)
            ->whereBetween(DB::raw('DATE(created_at)'), [$periodStart, $periodEnd])
            ->whereNotNull('work_task_id')
            ->orderBy('created_at');
        if ($taskIds !== null) {
            $tickQuery->whereIn('work_task_id', $taskIds);
        }
        foreach ($tickQuery->get(['work_task_id', 'created_at', 'payload']) as $e) {
            $ts = isset($e->payload['ticked_at']) ? strtotime($e->payload['ticked_at']) : $e->created_at->timestamp;
            $date = date('Y-m-d', $ts);
            if (! isset($out[$date])) {
                $out[$date] = $ts;
            }
        }
        $taskQuery = CongViecTask::where('user_id', $userId)
            ->where('completed', true)
            ->whereBetween('updated_at', [Carbon::parse($periodStart)->startOfDay(), Carbon::parse($periodEnd)->endOfDay()]);
        if ($taskIds !== null) {
            $taskQuery->whereIn('id', $taskIds);
        }
        foreach ($taskQuery->get(['id', 'updated_at']) as $t) {
            $date = $t->updated_at->format('Y-m-d');
            if (! isset($out[$date])) {
                $out[$date] = $t->updated_at->timestamp;
            }
        }
        ksort($out);

        return $out;
    }

    protected function computeVarianceScore(array $completionTimes): ?float
    {
        if (count($completionTimes) < 2) {
            return null;
        }
        $hours = [];
        foreach ($completionTimes as $ts) {
            $hours[] = (int) date('G', $ts) + (int) date('i', $ts) / 60.0;
        }
        $mean = array_sum($hours) / count($hours);
        $variance = 0.0;
        foreach ($hours as $h) {
            $variance += ($h - $mean) ** 2;
        }
        $variance = $variance / (count($hours) - 1);
        $std = $variance > 0 ? sqrt($variance) : 0.0;

        return round($std, 4);
    }

    protected function computeDriftSlope(array $completionTimes): ?float
    {
        if (count($completionTimes) < 2) {
            return null;
        }
        $dates = array_keys($completionTimes);
        $hours = [];
        foreach ($completionTimes as $ts) {
            $hours[] = (int) date('G', $ts) + (int) date('i', $ts) / 60.0;
        }
        $n = count($dates);
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;
        foreach ($dates as $i => $d) {
            $x = $i;
            $y = $hours[$i];
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }
        $denom = $n * $sumX2 - $sumX * $sumX;
        if (abs($denom) < 1e-9) {
            return null;
        }
        $slope = ($n * $sumXY - $sumX * $sumY) / $denom;

        return round((float) $slope, 6);
    }

    protected function estimateStreakRisk(int $userId, string $periodStart, string $periodEnd, ?float $driftSlope): ?string
    {
        if ($driftSlope === null) {
            return null;
        }
        if ($driftSlope > 0.1) {
            return 'high';
        }
        if ($driftSlope > 0.03) {
            return 'medium';
        }

        return 'low';
    }
}
