<?php

namespace App\Services;

use App\Models\CongViecTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LongTermProjectionService
{
    protected int $minDaysData;

    public function __construct()
    {
        $this->minDaysData = (int) config('behavior_intelligence.projection.min_days_data', 90);
    }

    /**
     * Dự báo xác suất duy trì 60/90 ngày. Trả về null nếu chưa đủ dữ liệu.
     *
     * @return array{probability_maintain_60d: float, probability_maintain_90d: float, risk_weeks: array, suggestion: string}|null
     */
    public function project(int $userId, bool $storeSnapshot = true): ?array
    {
        if (! config('behavior_intelligence.layers.long_term_projection', true)) {
            return null;
        }

        $oldest = CongViecTask::where('user_id', $userId)->min('created_at');
        if (! $oldest || Carbon::parse($oldest)->diffInDays(Carbon::now()) < $this->minDaysData) {
            return null;
        }

        $recoveryRow = DB::table('behavior_recovery_state')->where('user_id', $userId)->first();
        $trustRow = DB::table('behavior_trust_gradients')->where('user_id', $userId)->first();
        $cliRows = DB::table('behavior_cognitive_snapshots')
            ->where('user_id', $userId)
            ->orderByDesc('snapshot_date')
            ->limit(30)
            ->pluck('cli');
        $avgCli = $cliRows->isEmpty() ? 0.5 : $cliRows->avg();
        $recoveryDays = $recoveryRow ? (int) $recoveryRow->recovery_days : null;
        $trustConsistency = $trustRow ? (float) $trustRow->trust_consistency : 0.5;

        $baseP = 0.5 + $trustConsistency * 0.3 + (1 - min(1, $avgCli)) * 0.1;
        if ($recoveryDays !== null && $recoveryDays <= 3) {
            $baseP += 0.1;
        }
        $probability60 = min(0.98, round($baseP, 2));
        $probability90 = min(0.95, round($baseP * 0.95, 2));
        $riskWeeks = [];
        $suggestion = 'Với mô hình hiện tại, xác suất duy trì 60 ngày tiếp theo khoảng ' . round($probability60 * 100) . '%.';

        $result = [
            'probability_maintain_60d' => $probability60,
            'probability_maintain_90d' => $probability90,
            'risk_weeks' => $riskWeeks,
            'suggestion' => $suggestion,
        ];

        if ($storeSnapshot) {
            DB::table('behavior_projection_snapshots')->insert([
                'user_id' => $userId,
                'snapshot_at' => now(),
                'probabilities' => json_encode(['60d' => $probability60, '90d' => $probability90]),
                'risk_weeks' => json_encode($riskWeeks),
                'suggestion' => $suggestion,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $result;
    }

    /**
     * Lấy snapshot gần nhất (trong TTL) hoặc tính mới.
     */
    public function getOrCompute(int $userId): ?array
    {
        $ttlHours = (int) config('behavior_intelligence.projection.snapshot_ttl_hours', 24);
        $cutoff = Carbon::now()->subHours($ttlHours);
        $row = DB::table('behavior_projection_snapshots')
            ->where('user_id', $userId)
            ->where('snapshot_at', '>=', $cutoff)
            ->orderByDesc('snapshot_at')
            ->first();
        if ($row) {
            $p = json_decode($row->probabilities, true);

            return [
                'probability_maintain_60d' => (float) ($p['60d'] ?? 0),
                'probability_maintain_90d' => (float) ($p['90d'] ?? 0),
                'risk_weeks' => json_decode($row->risk_weeks, true) ?? [],
                'suggestion' => $row->suggestion,
            ];
        }

        return $this->project($userId, true);
    }
}
