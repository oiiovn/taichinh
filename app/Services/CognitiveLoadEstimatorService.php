<?php

namespace App\Services;

use App\Models\BehaviorEvent;
use App\Models\CongViecTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CognitiveLoadEstimatorService
{
    /**
     * Tính CLI cho user trong cửa sổ ngày (e.g. 7 ngày). Lưu vào behavior_cognitive_snapshots.
     */
    public function computeAndStore(int $userId, string $snapshotDate, int $windowDays = 7): float
    {
        if (! config('behavior_intelligence.layers.cognitive_load', true)) {
            return 0.0;
        }

        $end = Carbon::parse($snapshotDate)->endOfDay();
        $start = $end->copy()->subDays($windowDays)->startOfDay();

        $newTasks = CongViecTask::where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->count();
        $activeTasks = CongViecTask::where('user_id', $userId)
            ->where('completed', false)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('created_at', [$start, $end])
                    ->orWhereBetween('due_date', [$start->format('Y-m-d'), $end->format('Y-m-d')]);
            })
            ->count();
        $activeMinutes = $this->sumDwellMinutes($userId, $start->format('Y-m-d'), $end->format('Y-m-d'));

        $cli = $this->computeCLI($newTasks, $activeTasks, $activeMinutes);

        DB::table('behavior_cognitive_snapshots')->updateOrInsert(
            ['user_id' => $userId, 'snapshot_date' => $snapshotDate],
            [
                'cli' => $cli,
                'new_tasks_count' => $newTasks,
                'active_tasks_count' => $activeTasks,
                'active_minutes' => $activeMinutes,
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );

        return $cli;
    }

    protected function sumDwellMinutes(int $userId, string $from, string $to): int
    {
        $rows = BehaviorEvent::where('user_id', $userId)
            ->where('event_type', BehaviorEvent::TYPE_DWELL_MS)
            ->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->get(['payload']);
        $totalMs = 0;
        foreach ($rows as $r) {
            if (is_array($r->payload) && isset($r->payload['dwell_ms'])) {
                $totalMs += (int) $r->payload['dwell_ms'];
            }
        }

        return (int) round($totalMs / 60000);
    }

    /**
     * Heuristic: CLI cao khi nhiều task mới, nhiều active, ít thời gian active (overload).
     */
    protected function computeCLI(int $newTasks, int $activeTasks, int $activeMinutes): float
    {
        $score = 0.0;
        if ($newTasks >= 10) {
            $score += 0.3;
        } elseif ($newTasks >= 5) {
            $score += 0.2;
        } elseif ($newTasks >= 2) {
            $score += 0.1;
        }
        if ($activeTasks >= 20) {
            $score += 0.4;
        } elseif ($activeTasks >= 10) {
            $score += 0.25;
        } elseif ($activeTasks >= 5) {
            $score += 0.1;
        }
        if ($activeMinutes > 0 && $activeMinutes < 30 && ($newTasks + $activeTasks) > 5) {
            $score += 0.2;
        }
        return min(1.0, round($score, 4));
    }
}
