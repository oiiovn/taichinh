<?php

namespace App\Services;

use App\Models\CongViecTask;
use App\Models\WorkTaskInstance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Failure Detection: risk_score 0–1 (skip_streak_ratio, delay_ratio, completion_drop).
 * Tier: normal (0–0.3), warning (0.3–0.6), collapse (0.6–1.0). UI: 🟢 Ổn định, 🟠 Cảnh báo, 🔴 Nguy cơ sụt.
 */
class FailureDetectionService
{
    public const RISK_TIER_NORMAL = 'normal';
    public const RISK_TIER_WARNING = 'warning';
    public const RISK_TIER_COLLAPSE = 'collapse';

    protected function getSkipStreakThreshold(): int
    {
        return config('behavior_intelligence.execution_intelligence.failure_detection.skip_streak_threshold', 3);
    }

    protected function getDelayCountThreshold(): int
    {
        return config('behavior_intelligence.execution_intelligence.failure_detection.delay_count_threshold', 5);
    }

    /**
     * risk_score = skip_streak_ratio*0.4 + delay_ratio*0.3 + completion_drop*0.3
     *
     * @return array{
     *   at_risk: bool,
     *   risk_score: float,
     *   risk_tier: string,
     *   risk_label: string,
     *   skip_streak_days: int,
     *   delay_count_30d: int,
     *   completion_rate_30d: float,
     *   signals: array,
     *   suggestions: array,
     *   collapse_risk_message: string|null
     * }
     */
    public function detect(int $userId): array
    {
        $today = Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d');
        $from30 = Carbon::now('Asia/Ho_Chi_Minh')->subDays(30)->format('Y-m-d');

        $taskIds = CongViecTask::where('user_id', $userId)->pluck('id')->all();
        if (empty($taskIds)) {
            return $this->emptyResult();
        }

        $skipStreak = $this->getSkipStreakDays($userId, $taskIds, $today);
        $delayCount = $this->getDelayCount30d($userId, $taskIds, $from30, $today);
        $total30 = WorkTaskInstance::whereIn('work_task_id', $taskIds)
            ->whereBetween('instance_date', [$from30, $today])
            ->count();
        $completed30 = WorkTaskInstance::whereIn('work_task_id', $taskIds)
            ->whereBetween('instance_date', [$from30, $today])
            ->where('status', WorkTaskInstance::STATUS_COMPLETED)
            ->count();
        $completionRate30 = $total30 > 0 ? $completed30 / $total30 : 1.0;

        $skipThreshold = $this->getSkipStreakThreshold();
        $delayThreshold = $this->getDelayCountThreshold();
        $skipStreakRatio = min(1.0, $skipStreak / max(1, $skipThreshold));
        $delayRatio = min(1.0, $delayCount / max(1, $delayThreshold));
        $completionDrop = max(0.0, 1.0 - $completionRate30);

        $riskScore = round(
            $skipStreakRatio * 0.4 + $delayRatio * 0.3 + $completionDrop * 0.3,
            4
        );
        $riskScore = min(1.0, max(0.0, $riskScore));

        [$riskTier, $riskLabel] = $this->riskTierAndLabel($riskScore);

        $signals = [];
        if ($skipStreak >= $skipThreshold) {
            $signals[] = 'skip_streak';
        }
        if ($delayCount >= $delayThreshold) {
            $signals[] = 'high_delay';
        }

        $atRisk = $riskTier !== self::RISK_TIER_NORMAL;
        $suggestions = $this->getSuggestions($signals, $skipStreak, $delayCount);
        $collapseRiskMessage = $atRisk ? $this->collapseRiskMessage($signals, $skipStreak, $delayCount) : null;

        $riskScore7dAgo = $this->getRiskScoreForPastDate($userId, $taskIds, 7);
        $riskTrend = $this->riskTrend($riskScore, $riskScore7dAgo);
        $riskDelta = $riskScore7dAgo !== null ? round($riskScore - $riskScore7dAgo, 2) : null;

        return [
            'at_risk' => $atRisk,
            'risk_score' => $riskScore,
            'risk_score_7d_ago' => $riskScore7dAgo,
            'risk_trend' => $riskTrend,
            'risk_delta' => $riskDelta,
            'risk_tier' => $riskTier,
            'risk_label' => $riskLabel,
            'skip_streak_days' => $skipStreak,
            'delay_count_30d' => $delayCount,
            'completion_rate_30d' => round($completionRate30, 4),
            'signals' => $signals,
            'suggestions' => $suggestions,
            'collapse_risk_message' => $collapseRiskMessage,
        ];
    }

    protected function riskTrend(float $current, ?float $past): string
    {
        if ($past === null) {
            return 'stable';
        }
        $delta = $current - $past;
        if ($delta > 0.05) {
            return 'rising';
        }
        if ($delta < -0.05) {
            return 'improving';
        }
        return 'stable';
    }

    /** Risk score tính cho ngày trong quá khứ (để so sánh trend). */
    protected function getRiskScoreForPastDate(int $userId, array $taskIds, int $daysAgo): ?float
    {
        $asOf = Carbon::now('Asia/Ho_Chi_Minh')->subDays($daysAgo)->format('Y-m-d');
        $from30 = Carbon::now('Asia/Ho_Chi_Minh')->subDays($daysAgo + 30)->format('Y-m-d');

        $skipStreak = $this->getSkipStreakDays($userId, $taskIds, $asOf);
        $delayCount = $this->getDelayCount30d($userId, $taskIds, $from30, $asOf);
        $total30 = WorkTaskInstance::whereIn('work_task_id', $taskIds)
            ->whereBetween('instance_date', [$from30, $asOf])
            ->count();
        $completed30 = WorkTaskInstance::whereIn('work_task_id', $taskIds)
            ->whereBetween('instance_date', [$from30, $asOf])
            ->where('status', WorkTaskInstance::STATUS_COMPLETED)
            ->count();
        $completionRate30 = $total30 > 0 ? $completed30 / $total30 : 1.0;

        $skipThreshold = $this->getSkipStreakThreshold();
        $delayThreshold = $this->getDelayCountThreshold();
        $skipStreakRatio = min(1.0, $skipStreak / max(1, $skipThreshold));
        $delayRatio = min(1.0, $delayCount / max(1, $delayThreshold));
        $completionDrop = max(0.0, 1.0 - $completionRate30);

        return min(1.0, max(0.0, round($skipStreakRatio * 0.4 + $delayRatio * 0.3 + $completionDrop * 0.3, 4)));
    }

    protected function riskTierAndLabel(float $riskScore): array
    {
        if ($riskScore < 0.3) {
            return [self::RISK_TIER_NORMAL, 'Ổn định'];
        }
        if ($riskScore < 0.6) {
            return [self::RISK_TIER_WARNING, 'Cảnh báo'];
        }
        return [self::RISK_TIER_COLLAPSE, 'Nguy cơ sụt'];
    }

    /** Số ngày gần nhất (tính từ hôm nay lùi về) mà user có ít nhất 1 instance và tất cả đều skipped hoặc pending (không completed). */
    protected function getSkipStreakDays(int $userId, array $taskIds, string $today): int
    {
        $instances = WorkTaskInstance::whereIn('work_task_id', $taskIds)
            ->where('instance_date', '<=', $today)
            ->orderByDesc('instance_date')
            ->get()
            ->groupBy('instance_date');

        $current = Carbon::parse($today)->startOfDay();
        $streak = 0;
        $maxDays = 14;

        for ($i = 0; $i < $maxDays; $i++) {
            $d = $current->copy()->subDays($i)->format('Y-m-d');
            $dayInstances = $instances->get($d, collect());
            if ($dayInstances->isEmpty()) {
                continue;
            }
            $anyCompleted = $dayInstances->contains('status', WorkTaskInstance::STATUS_COMPLETED);
            if ($anyCompleted) {
                break;
            }
            $streak++;
        }

        return $streak;
    }

    /** Số instance trong 30d có completed_at > instance_date (trễ ngày). */
    protected function getDelayCount30d(int $userId, array $taskIds, string $from, string $to): int
    {
        return (int) DB::table('work_task_instances')
            ->join('work_tasks', 'work_task_instances.work_task_id', '=', 'work_tasks.id')
            ->where('work_tasks.user_id', $userId)
            ->whereIn('work_task_instances.work_task_id', $taskIds)
            ->where('work_task_instances.status', WorkTaskInstance::STATUS_COMPLETED)
            ->whereNotNull('work_task_instances.completed_at')
            ->whereBetween('work_task_instances.instance_date', [$from, $to])
            ->whereRaw('DATE(work_task_instances.completed_at) > work_task_instances.instance_date')
            ->count();
    }

    protected function getSuggestions(array $signals, int $skipStreak, int $delayCount): array
    {
        $suggestions = [];
        if (in_array('skip_streak', $signals)) {
            $suggestions[] = 'Giảm scope: chọn 1–2 việc quan trọng nhất mỗi ngày.';
            $suggestions[] = 'Chia task lớn thành các bước nhỏ, dễ hoàn thành.';
        }
        if (in_array('high_delay', $signals)) {
            $suggestions[] = 'Đổi giờ làm: thử làm việc quan trọng vào buổi sáng.';
            $suggestions[] = 'Giảm số task trong ngày để tránh trễ hạn.';
        }
        return $suggestions;
    }

    protected function collapseRiskMessage(array $signals, int $skipStreak, int $delayCount): string
    {
        $parts = [];
        if (in_array('skip_streak', $signals)) {
            $parts[] = "Bạn đã {$skipStreak} ngày liên tiếp không hoàn thành cam kết.";
        }
        if (in_array('high_delay', $signals)) {
            $parts[] = "Trong 30 ngày có {$delayCount} lần hoàn thành trễ hạn.";
        }
        return '⚠️ Nguy cơ trượt cam kết: ' . implode(' ', $parts) . ' Hệ thống gợi ý điều chỉnh phía dưới.';
    }

    protected function emptyResult(): array
    {
        return [
            'at_risk' => false,
            'risk_score' => 0.0,
            'risk_score_7d_ago' => null,
            'risk_trend' => 'stable',
            'risk_tier' => self::RISK_TIER_NORMAL,
            'risk_label' => 'Ổn định',
            'skip_streak_days' => 0,
            'delay_count_30d' => 0,
            'completion_rate_30d' => 1.0,
            'signals' => [],
            'suggestions' => [],
            'collapse_risk_message' => null,
        ];
    }
}
