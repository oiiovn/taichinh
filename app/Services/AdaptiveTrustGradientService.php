<?php

namespace App\Services;

use App\Models\CongViecTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdaptiveTrustGradientService
{
    protected float $alpha = 0.2;

    /**
     * Tỷ lệ hoàn thành gần đây (7 ngày): số task completed / 7, clamp 0..1.
     * $programId = null: toàn bộ task; có giá trị: chỉ task thuộc program.
     */
    public function getRecentCompletionRate(int $userId, int $days = 7, ?int $programId = null): float
    {
        $from = Carbon::now()->subDays($days)->startOfDay();
        $q = CongViecTask::where('user_id', $userId)
            ->where('completed', true)
            ->where('updated_at', '>=', $from);
        if ($programId !== null) {
            $q->where('program_id', $programId);
        }
        $count = $q->count();

        return min(1.0, (float) $count / (float) max(1, $days));
    }

    /**
     * Cập nhật 3 trục trust từ completion, P(real), temporal/recovery.
     * $programId = null: trust toàn cục (user); có giá trị: trust theo program.
     */
    public function update(int $userId, ?float $pReal = null, ?float $completionRate = null, ?float $varianceScore = null, ?int $recoveryDays = null, ?int $programId = null): void
    {
        if (! config('behavior_intelligence.layers.adaptive_trust', true)) {
            return;
        }

        $query = DB::table('behavior_trust_gradients')->where('user_id', $userId);
        if ($programId === null) {
            $query->whereNull('program_id');
        } else {
            $query->where('program_id', $programId);
        }
        $row = $query->first();

        $prevExecution = $row ? (float) $row->trust_execution : 0.5;
        $prevHonesty = $row ? (float) $row->trust_honesty : 0.5;
        $prevConsistency = $row ? (float) $row->trust_consistency : 0.5;

        $trustExecution = $completionRate !== null
            ? $this->ewma($prevExecution, $completionRate, $this->alpha)
            : $prevExecution;
        $trustHonesty = $pReal !== null
            ? $this->ewma($prevHonesty, $pReal, $this->alpha)
            : $prevHonesty;
        $trustConsistency = $varianceScore !== null
            ? $this->ewma($prevConsistency, max(0, 1 - $varianceScore), $this->alpha)
            : ($recoveryDays !== null && $recoveryDays <= 3 ? $this->ewma($prevConsistency, 0.8, $this->alpha) : $prevConsistency);
        $trustExecution = round(max(0, min(1, $trustExecution)), 4);
        $trustHonesty = round(max(0, min(1, $trustHonesty)), 4);
        $trustConsistency = round(max(0, min(1, $trustConsistency)), 4);

        $keys = ['user_id' => $userId, 'program_id' => $programId];
        $values = [
            'trust_execution' => $trustExecution,
            'trust_honesty' => $trustHonesty,
            'trust_consistency' => $trustConsistency,
            'updated_at' => now(),
            'created_at' => DB::raw('COALESCE(created_at, NOW())'),
        ];
        if (Schema::hasColumn('behavior_trust_gradients', 'program_id')) {
            DB::table('behavior_trust_gradients')->updateOrInsert($keys, $values);
        } else {
            DB::table('behavior_trust_gradients')->updateOrInsert(['user_id' => $userId], $values);
        }
    }

    protected function ewma(float $prev, float $value, float $alpha): float
    {
        return $alpha * $value + (1 - $alpha) * $prev;
    }

    /**
     * Lấy variance và recovery gần nhất từ snapshot (cho trust real-time).
     * $programId = null: user-level; có giá trị: program-level (nếu bảng có cột program_id).
     *
     * @return array{variance_score: float|null, recovery_days: int|null}
     */
    public function getLatestVarianceAndRecovery(int $userId, ?int $programId = null): array
    {
        $aggQuery = DB::table('behavior_temporal_aggregates')->where('user_id', $userId);
        $recQuery = DB::table('behavior_recovery_state')->where('user_id', $userId);
        if (Schema::hasColumn('behavior_temporal_aggregates', 'program_id')) {
            if ($programId === null) {
                $aggQuery->whereNull('program_id');
            } else {
                $aggQuery->where('program_id', $programId);
            }
        }
        if (Schema::hasColumn('behavior_recovery_state', 'program_id')) {
            if ($programId === null) {
                $recQuery->whereNull('program_id');
            } else {
                $recQuery->where('program_id', $programId);
            }
        }
        $agg = $aggQuery->orderByDesc('period_end')->first();
        $rec = $recQuery->first();

        return [
            'variance_score' => $agg && $agg->variance_score !== null ? (float) $agg->variance_score : null,
            'recovery_days' => $rec && $rec->recovery_days !== null ? (int) $rec->recovery_days : null,
        ];
    }

    /**
     * Lấy gradient hiện tại. $programId = null: trust toàn cục.
     *
     * @return array{trust_execution: float, trust_honesty: float, trust_consistency: float}|null
     */
    public function get(int $userId, ?int $programId = null): ?array
    {
        $q = DB::table('behavior_trust_gradients')->where('user_id', $userId);
        if (Schema::hasColumn('behavior_trust_gradients', 'program_id')) {
            $q->where('program_id', $programId);
        }
        $row = $q->first();
        if (! $row) {
            return null;
        }

        return [
            'trust_execution' => (float) $row->trust_execution,
            'trust_honesty' => (float) $row->trust_honesty,
            'trust_consistency' => (float) $row->trust_consistency,
        ];
    }
}
