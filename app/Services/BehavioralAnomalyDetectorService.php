<?php

namespace App\Services;

use App\Models\CongViecTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BehavioralAnomalyDetectorService
{
    protected float $sigmaThreshold;
    protected int $maxMessagePerDay;

    public function __construct()
    {
        $cfg = config('behavior_intelligence.anomaly', []);
        $this->sigmaThreshold = (float) ($cfg['sigma_threshold'] ?? 2.0);
        $this->maxMessagePerDay = (int) ($cfg['max_message_per_day'] ?? 1);
    }

    /**
     * Kiểm tra và ghi log nếu phát hiện bất thường (lệch > 2 sigma so với baseline chu kỳ).
     *
     * @return array{detected: bool, message_key: string|null}
     */
    public function detectAndLog(int $userId, string $date): array
    {
        if (! config('behavior_intelligence.layers.behavioral_anomaly', true)) {
            return ['detected' => false, 'message_key' => null];
        }

        $today = Carbon::parse($date);
        $currentWeekCompletions = CongViecTask::where('user_id', $userId)
            ->where('completed', true)
            ->whereBetween('updated_at', [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()])
            ->count();
        $weeklyCounts = $this->weeklyCompletionCounts($userId, 12);
        if (count($weeklyCounts) < 2) {
            return ['detected' => false, 'message_key' => null];
        }
        $mean = array_sum($weeklyCounts) / count($weeklyCounts);
        $variance = array_sum(array_map(fn ($x) => ($x - $mean) ** 2, $weeklyCounts)) / (count($weeklyCounts) - 1);
        $std = $variance > 0 ? sqrt($variance) : 0;
        $isAnomaly = $std > 0 && ($currentWeekCompletions < $mean - $this->sigmaThreshold * $std);

        if (! $isAnomaly) {
            return ['detected' => false, 'message_key' => null];
        }

        $alreadyToday = DB::table('behavior_anomaly_logs')
            ->where('user_id', $userId)
            ->whereDate('detected_at', $date)
            ->count();
        if ($alreadyToday >= $this->maxMessagePerDay) {
            return ['detected' => true, 'message_key' => 'rhythm_shift_soft'];
        }

        DB::table('behavior_anomaly_logs')->insert([
            'user_id' => $userId,
            'detected_at' => $today,
            'period_type' => 'day_of_week',
            'message_key' => 'rhythm_shift_soft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['detected' => true, 'message_key' => 'rhythm_shift_soft'];
    }

    /** @return array<int> completion count per week (last N weeks) */
    protected function weeklyCompletionCounts(int $userId, int $weeks): array
    {
        $end = Carbon::now()->endOfWeek();
        $counts = [];
        for ($i = 0; $i < $weeks; $i++) {
            $start = $end->copy()->startOfWeek()->subWeeks($i);
            $weekEnd = $start->copy()->endOfWeek();
            $counts[] = CongViecTask::where('user_id', $userId)
                ->where('completed', true)
                ->whereBetween('updated_at', [$start, $weekEnd])
                ->count();
        }

        return $counts;
    }

    public function getSoftMessage(string $messageKey): string
    {
        return match ($messageKey) {
            'rhythm_shift_soft' => 'Có sự thay đổi nhẹ trong nhịp kỷ luật. Bạn có muốn điều chỉnh mục tiêu hôm nay?',
            default => 'Có sự thay đổi nhẹ trong nhịp kỷ luật. Bạn có muốn điều chỉnh mục tiêu hôm nay?',
        };
    }
}
