<?php

namespace App\Services;

use App\Models\WorkTaskInstance;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Execution Intelligence: Priority Score Engine.
 * Chuyển task list thành decision list — "Tôi nên làm cái nào trước?"
 *
 * priority_score = urgency*0.35 + impact*0.30 + streak_risk*0.15 + program_importance*0.10 + overdue_penalty*0.10
 */
class TaskPriorityEngineService
{
    /**
     * Dynamic Priority: adaptive weights theo behavior profile.
     * procrastinator → urgency tăng; burnout_risk → impact giảm.
     */
    protected function weights(?array $behaviorProfile = null): array
    {
        $c = config('behavior_intelligence.execution_intelligence.priority_engine', []);
        $w = [
            'urgency' => $c['weight_urgency'] ?? 0.30,
            'impact' => $c['weight_impact'] ?? 0.25,
            'streak_risk' => $c['weight_streak_risk'] ?? 0.15,
            'program' => $c['weight_program'] ?? 0.10,
            'overdue' => $c['weight_overdue'] ?? 0.10,
            'deadline_pressure' => $c['weight_deadline_pressure'] ?? 0.10,
        ];
        if ($behaviorProfile && isset($behaviorProfile['profile'])) {
            $profile = $behaviorProfile['profile'];
            if ($profile === BehaviorProfileService::PROFILE_PROCRASTINATOR) {
                $w['urgency'] = min(0.5, $w['urgency'] + 0.10);
            }
            if ($profile === BehaviorProfileService::PROFILE_BURNOUT_RISK) {
                $w['impact'] = max(0.15, $w['impact'] - 0.10);
            }
        }
        return $w;
    }

    protected function thresholds(): array
    {
        $c = config('behavior_intelligence.execution_intelligence.priority_engine', []);
        return [
            'high' => $c['threshold_high'] ?? 0.65,
            'medium' => $c['threshold_medium'] ?? 0.40,
        ];
    }

    /**
     * Tính priority_score cho từng instance, sắp xếp giảm dần, gán tier.
     * Dynamic weights khi truyền $behaviorProfile.
     *
     * @param  array<int, int>  $taskStreaks  work_task_id => streak
     * @param  array{profile: string}|null  $behaviorProfile  Để điều chỉnh weight (procrastinator/burnout)
     * @return array{sorted: Collection, tiers: array{high: Collection, medium: Collection, low: Collection}, scores: array}
     */
    public function scoreAndTierTodayInstances(
        Collection $instances,
        array $taskStreaks,
        string $todayHcm,
        ?int $activeProgramId = null,
        ?array $behaviorProfile = null
    ): array {
        $today = Carbon::parse($todayHcm)->startOfDay();
        $scores = [];
        $w = $this->weights($behaviorProfile);

        $now = Carbon::now('Asia/Ho_Chi_Minh');
        $boostHours = (float) (config('behavior_intelligence.execution_intelligence.priority_engine.deadline_boost_hours', 4));

        foreach ($instances as $instance) {
            $task = $instance->task;
            $urgency = $this->urgencyComponent($task, $today);
            $impact = $this->impactComponent($task);
            $streakRisk = $this->streakRiskComponent($taskStreaks[$task->id] ?? 0);
            $programImportance = $this->programImportanceComponent($task, $activeProgramId);
            $overduePenalty = $this->overduePenaltyComponent($task, $today);
            $deadlinePressure = $this->deadlinePressureComponent($task, $today, $now, $boostHours);
            $agePenalty = $this->agePenaltyComponent($task, $today);
            $score = $urgency * $w['urgency']
                + $impact * $w['impact']
                + $streakRisk * $w['streak_risk']
                + $programImportance * $w['program']
                + $overduePenalty * $w['overdue']
                + $deadlinePressure * $w['deadline_pressure']
                + $agePenalty;

            $scores[$instance->id] = [
                'score' => round(min(1.0, $score), 4),
                'urgency' => $urgency,
                'impact' => $impact,
                'streak_risk' => $streakRisk,
                'program_importance' => $programImportance,
                'overdue_penalty' => $overduePenalty,
                'deadline_pressure' => $deadlinePressure,
                'age_penalty' => $agePenalty,
            ];
        }

        $th = $this->thresholds();
        $sorted = $instances->sortByDesc(fn (WorkTaskInstance $i) => $scores[$i->id]['score'])->values();
        $tiers = [
            'high' => $sorted->filter(fn (WorkTaskInstance $i) => $scores[$i->id]['score'] >= $th['high']),
            'medium' => $sorted->filter(fn (WorkTaskInstance $i) => $scores[$i->id]['score'] >= $th['medium'] && $scores[$i->id]['score'] < $th['high']),
            'low' => $sorted->filter(fn (WorkTaskInstance $i) => $scores[$i->id]['score'] < $th['medium']),
        ];

        return [
            'sorted' => $sorted,
            'tiers' => $tiers,
            'scores' => $scores,
        ];
    }

    /** Urgency 0–1: priority (1=khẩn cấp) + due_time sớm trong ngày tăng điểm */
    protected function urgencyComponent($task, Carbon $today): float
    {
        $base = 0.5;
        if ($task->priority !== null) {
            $base = 1.0 - ((int) $task->priority - 1) / 3.0;
            $base = max(0.2, min(1.0, $base));
        }
        if ($task->due_date && Carbon::parse($task->due_date)->isSameDay($today) && $task->due_time) {
            $dueMinutes = $this->timeToMinutes($task->due_time);
            $nowMinutes = $today->copy()->setTimezone('Asia/Ho_Chi_Minh')->hour * 60 + $today->copy()->setTimezone('Asia/Ho_Chi_Minh')->minute;
            $hoursUntil = ($dueMinutes - $nowMinutes) / 60.0;
            if ($hoursUntil <= 2 && $hoursUntil >= 0) {
                $base = min(1.0, $base + 0.3);
            }
        }
        return $base;
    }

    protected function timeToMinutes(?string $time): int
    {
        if (! $time) {
            return 0;
        }
        $parts = explode(':', substr($time, 0, 5));
        return (int) ($parts[0] ?? 0) * 60 + (int) ($parts[1] ?? 0);
    }

    /** Impact 0–1: high=1, medium=0.6, low=0.3 */
    protected function impactComponent($task): float
    {
        return match ($task->impact ?? '') {
            'high' => 1.0,
            'medium' => 0.6,
            'low' => 0.3,
            default => 0.2,
        };
    }

    /** Streak risk 0–1: streak càng cao thì không làm hôm nay càng "mất nhiều" → ưu tiên cao */
    protected function streakRiskComponent(int $streak): float
    {
        return min(1.0, $streak / 7.0);
    }

    /** Program importance 0–1: thuộc program (đặc biệt active) = cao hơn */
    protected function programImportanceComponent($task, ?int $activeProgramId): float
    {
        if (! $task->program_id) {
            return 0.2;
        }
        return $task->program_id == $activeProgramId ? 1.0 : 0.5;
    }

    /** Overdue penalty 0–1: due_date đã qua → ưu tiên làm trước */
    protected function overduePenaltyComponent($task, Carbon $today): float
    {
        if (! $task->due_date) {
            return 0.0;
        }
        $due = Carbon::parse($task->due_date)->startOfDay();
        return $due->lt($today) ? 1.0 : 0.0;
    }

    /**
     * Deadline pressure 0–1: hạn trong ngày càng gần càng tăng điểm.
     * Rule: deadline < 4h → auto boost (pressure = 1).
     * Công thức: min(1, 1 / hours_remaining).
     */
    protected function deadlinePressureComponent($task, Carbon $today, Carbon $now, float $boostHours): float
    {
        if (! $task->due_date || ! $task->due_time) {
            return 0.0;
        }
        $dueDate = Carbon::parse($task->due_date)->startOfDay();
        if (! $dueDate->isSameDay($today)) {
            return 0.0;
        }
        $dueMinutes = $this->timeToMinutes($task->due_time);
        $nowMinutes = $now->hour * 60 + $now->minute;
        $hoursRemaining = ($dueMinutes - $nowMinutes) / 60.0;
        if ($hoursRemaining <= 0) {
            return 1.0;
        }
        if ($hoursRemaining < $boostHours) {
            return 1.0;
        }
        return (float) min(1.0, 1.0 / $hoursRemaining);
    }

    /** Priority aging: task càng cũ (max created_at, updated_at) càng tăng nhẹ score. Chỉnh sửa task reset “tuổi” để tránh penalty sau khi user đã cập nhật. age_penalty = min(age_days * 0.02, 0.1). */
    protected function agePenaltyComponent($task, Carbon $today): float
    {
        $ref = $task->updated_at && $task->created_at && $task->updated_at->gt($task->created_at)
            ? $task->updated_at
            : ($task->created_at ?? $task->updated_at);
        if (! $ref) {
            return 0.0;
        }
        $refDay = Carbon::parse($ref)->startOfDay();
        $ageDays = (int) $today->diffInDays($refDay, false);
        if ($ageDays <= 0) {
            return 0.0;
        }
        return min(0.1, $ageDays * 0.02);
    }
}
