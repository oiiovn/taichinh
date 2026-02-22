<?php

namespace App\Services;

use App\Models\BehaviorProgram;
use App\Models\CongViecTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BehaviorProgramProgressService
{
    /**
     * Tính completion rate trong khoảng ngày (số ngày có ≥1 task completed / tổng ngày).
     */
    public function getCompletionRateInRange(int $userId, int $programId, string $from, string $to): float
    {
        $tasks = CongViecTask::where('user_id', $userId)
            ->where('program_id', $programId)
            ->where('completed', true)
            ->whereBetween('updated_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->get(['updated_at']);
        $daysWithCompletion = $tasks->map(fn ($t) => $t->updated_at->format('Y-m-d'))->unique()->count();
        $totalDays = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;

        return $totalDays > 0 ? min(1.0, (float) $daysWithCompletion / (float) $totalDays) : 0.0;
    }

    /**
     * Tính Program Integrity Score (heuristic: completion consistency + trust + recovery + variance).
     *
     * @return array{integrity_score: float, completion_rate: float, outcome: string|null}
     */
    public function computeIntegrity(int $userId, int $programId): array
    {
        $program = BehaviorProgram::where('id', $programId)->where('user_id', $userId)->first();
        if (! $program) {
            return ['integrity_score' => 0.0, 'completion_rate' => 0.0, 'outcome' => null];
        }

        $start = $program->start_date->format('Y-m-d');
        $end = $program->getEndDateResolved()->format('Y-m-d');
        $completionRate = $this->getCompletionRateInRange($userId, $programId, $start, $end);

        $trust = app(AdaptiveTrustGradientService::class)->get($userId, $programId);
        $trustScore = $trust ? ($trust['trust_execution'] + $trust['trust_honesty'] + $trust['trust_consistency']) / 3.0 : 0.5;

        $extra = app(AdaptiveTrustGradientService::class)->getLatestVarianceAndRecovery($userId, $programId);
        $variancePenalty = $extra['variance_score'] ? (float) $extra['variance_score'] * 0.2 : 0;
        $recoveryBonus = ($extra['recovery_days'] !== null && $extra['recovery_days'] <= 3) ? 0.1 : 0;

        $integrity = $completionRate * 0.5 + $trustScore * 0.4 - $variancePenalty + $recoveryBonus;
        $integrity = max(0, min(1, round($integrity, 4)));

        $outcome = null;
        if ($program->status === BehaviorProgram::STATUS_COMPLETED) {
            $outcome = $integrity >= 0.8 ? 'complete' : ($integrity >= 0.5 ? 'complete_with_drift' : 'partial');
        } elseif ($program->status === BehaviorProgram::STATUS_FAILED) {
            $outcome = 'failed';
        }

        return [
            'integrity_score' => $integrity,
            'completion_rate' => $completionRate,
            'outcome' => $outcome,
        ];
    }

    /**
     * Lưu snapshot tiến độ program (gọi từ job hoặc on-demand).
     */
    public function storeSnapshot(int $userId, int $programId, string $date): void
    {
        $result = $this->computeIntegrity($userId, $programId);
        if (! \Illuminate\Support\Facades\Schema::hasTable('behavior_program_snapshots')) {
            return;
        }
        DB::table('behavior_program_snapshots')->updateOrInsert(
            ['program_id' => $programId, 'snapshot_date' => $date],
            [
                'user_id' => $userId,
                'integrity_score' => $result['integrity_score'],
                'completion_rate' => $result['completion_rate'],
                'outcome' => $result['outcome'],
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );
    }

    /**
     * Tiến độ hiển thị UI: ngày đã qua, ngày đạt target, completion rate, integrity.
     */
    public function getProgressForUi(int $userId, int $programId): array
    {
        $program = BehaviorProgram::where('id', $programId)->where('user_id', $userId)->first();
        if (! $program) {
            return [];
        }

        $start = Carbon::parse($program->start_date);
        $end = $program->getEndDateResolved();
        $today = Carbon::today();
        $daysTotal = $start->diffInDays($end) + 1;
        $daysElapsed = $today->gte($end) ? $daysTotal : $start->diffInDays($today) + 1;

        $to = $today->gt($end) ? $end->format('Y-m-d') : $today->format('Y-m-d');
        $completionRate = $this->getCompletionRateInRange($userId, $programId, $start->format('Y-m-d'), $to);
        $daysWithCompletion = (int) round($completionRate * $daysElapsed);
        $integrity = $this->computeIntegrity($userId, $programId);

        return [
            'program' => $program,
            'days_total' => $daysTotal,
            'days_elapsed' => min($daysElapsed, $daysTotal),
            'days_with_completion' => $daysWithCompletion,
            'completion_rate' => $completionRate,
            'integrity_score' => $integrity['integrity_score'],
            'outcome' => $integrity['outcome'],
        ];
    }
}
