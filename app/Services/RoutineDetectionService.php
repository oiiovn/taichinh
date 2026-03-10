<?php

namespace App\Services;

use App\Models\CongViecTask;
use App\Models\WorkTaskInstance;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Routine Detection Engine — nhẹ, chỉ thống kê completed_at theo giờ.
 * Không cần AI: median time, std_dev, slot (morning/work/afternoon/evening), confidence.
 */
class RoutineDetectionService
{
    public const SLOT_MORNING = 'morning';
    public const SLOT_WORK = 'work';
    public const SLOT_AFTERNOON = 'afternoon';
    public const SLOT_EVENING = 'evening';

    /** Ranh giới slot (phút từ 00:00): 05:00=300, 09:00=540, 13:00=780, 18:00=1080, 23:00=1380 */
    protected const SLOT_BOUNDS = [
        self::SLOT_MORNING => [300, 540],
        self::SLOT_WORK => [540, 780],
        self::SLOT_AFTERNOON => [780, 1080],
        self::SLOT_EVENING => [1080, 1380],
    ];

    protected function config(string $key, $default)
    {
        return config('behavior_intelligence.routine_detection.' . $key, $default);
    }

    /**
     * Lấy phút trong ngày từ completed_at (timezone HCM).
     */
    protected function minuteOfDay(?Carbon $completedAt): ?int
    {
        if (! $completedAt) {
            return null;
        }
        $t = $completedAt->copy()->setTimezone('Asia/Ho_Chi_Minh');

        return $t->hour * 60 + $t->minute;
    }

    /**
     * Median của mảng số.
     */
    protected function median(Collection $values): float
    {
        $sorted = $values->sort()->values();
        $n = $sorted->count();
        if ($n === 0) {
            return 0.0;
        }
        if ($n % 2 === 1) {
            return (float) $sorted->get((int) floor($n / 2));
        }

        return ($sorted->get($n / 2 - 1) + $sorted->get($n / 2)) / 2.0;
    }

    /**
     * Độ lệch chuẩn (phút).
     */
    protected function stdDev(Collection $values): float
    {
        $n = $values->count();
        if ($n < 2) {
            return 0.0;
        }
        $mean = $values->avg();
        $variance = $values->map(fn ($x) => ($x - $mean) ** 2)->avg();

        return sqrt($variance);
    }

    /**
     * Median Absolute Deviation (phút) — ổn định hơn với outlier.
     */
    protected function mad(Collection $values): float
    {
        $n = $values->count();
        if ($n < 2) {
            return 0.0;
        }
        $med = $this->median($values);
        $deviations = $values->map(fn ($x) => abs($x - $med));

        return 1.4826 * $this->median($deviations);
    }

    /**
     * Gán slot từ median phút (05:00–09:00 morning, 09–13 work, 13–18 afternoon, 18–23 evening).
     */
    protected function slotFromMedian(float $medianMinutes): string
    {
        foreach (self::SLOT_BOUNDS as $slot => [$lo, $hi]) {
            if ($medianMinutes >= $lo && $medianMinutes < $hi) {
                return $slot;
            }
        }
        if ($medianMinutes < 300) {
            return self::SLOT_EVENING;
        }

        return self::SLOT_EVENING;
    }

    /**
     * Chuỗi thời gian "HH:MM" từ phút trong ngày.
     */
    protected function minuteToTimeString(float $minutes): string
    {
        $m = (int) round($minutes);
        $h = (int) floor($m / 60) % 24;
        $min = $m % 60;

        return sprintf('%02d:%02d', $h, $min);
    }

    /**
     * Routine cho một task: median, std_dev, slot, stability, confidence.
     *
     * @return array{median_minutes: float, median_time: string, std_dev: float, mad: float, slot: string, stability: float, confidence: float, sample_count: int}|null
     */
    public function getRoutineForTask(int $taskId): ?array
    {
        $minSamples = (int) $this->config('min_samples', 3);
        $maxSamples = (int) $this->config('max_samples', 50);
        $stabilityDenom = (float) $this->config('stability_denom_minutes', 120);
        $confidenceSampleCap = (int) $this->config('confidence_sample_cap', 7);

        $instances = WorkTaskInstance::where('work_task_id', $taskId)
            ->where('status', WorkTaskInstance::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->limit($maxSamples)
            ->get();

        $minutes = $instances->map(fn ($i) => $this->minuteOfDay($i->completed_at))->filter(fn ($m) => $m !== null)->values();
        if ($minutes->count() < $minSamples) {
            return null;
        }

        $medianMinutes = $this->median($minutes);
        $stdDev = $this->stdDev($minutes);
        $mad = $this->mad($minutes);
        $dispersion = $stdDev > 0 ? $stdDev : $mad;
        $stability = max(0.0, min(1.0, 1.0 - ($dispersion / $stabilityDenom)));
        $sampleWeight = min(1.0, $minutes->count() / $confidenceSampleCap);
        $confidence = $sampleWeight * $stability;
        $slot = $this->slotFromMedian($medianMinutes);

        $driftDetected = false;
        $driftConfidence = 0.0;
        if (config('behavior_intelligence.enabled', true)) {
            $drift = app(BehaviorDriftService::class)->getDriftForTask($taskId);
            if ($drift && $drift['drift_detected'] && ! $drift['is_temporary']) {
                $driftDetected = true;
                $driftConfidence = $drift['drift_confidence'];
                $medianMinutes = $drift['median_short'];
                $slot = $this->slotFromMedian($medianMinutes);
                $confidence *= $drift['routine_decay'];
            }
        }

        return [
            'median_minutes' => $medianMinutes,
            'median_time' => $this->minuteToTimeString($medianMinutes),
            'std_dev' => round($stdDev, 2),
            'mad' => round($mad, 2),
            'slot' => $slot,
            'stability' => round($stability, 4),
            'confidence' => round(min(1.0, $confidence), 4),
            'sample_count' => $minutes->count(),
            'drift_detected' => $driftDetected,
            'drift_confidence' => $driftConfidence,
        ];
    }

    /**
     * Tất cả routine của user (task có confidence >= threshold), nhóm theo slot.
     *
     * @return array{morning: array, work: array, afternoon: array, evening: array, by_task: array}
     */
    public function getRoutinesForUser(int $userId): array
    {
        $threshold = (float) $this->config('confidence_threshold', 0.5);
        $taskIds = WorkTaskInstance::whereHas('task', fn ($q) => $q->where('user_id', $userId))
            ->where('status', WorkTaskInstance::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->distinct()
            ->pluck('work_task_id');

        $bySlot = [
            self::SLOT_MORNING => [],
            self::SLOT_WORK => [],
            self::SLOT_AFTERNOON => [],
            self::SLOT_EVENING => [],
        ];
        $byTask = [];

        foreach ($taskIds as $taskId) {
            $r = $this->getRoutineForTask($taskId);
            if (! $r || $r['confidence'] < $threshold) {
                continue;
            }
            $task = CongViecTask::find($taskId);
            $row = [
                'task_id' => $taskId,
                'title' => $task?->title ?? '',
                'median_time' => $r['median_time'],
                'median_minutes' => $r['median_minutes'],
                'slot' => $r['slot'],
                'confidence' => $r['confidence'],
                'stability' => $r['stability'],
                'sample_count' => $r['sample_count'],
                'drift_detected' => $r['drift_detected'] ?? false,
                'drift_confidence' => $r['drift_confidence'] ?? 0,
            ];
            $byTask[$taskId] = $row;
            if (isset($bySlot[$r['slot']])) {
                $bySlot[$r['slot']][] = $row;
            }
        }

        foreach (array_keys($bySlot) as $slot) {
            usort($bySlot[$slot], fn ($a, $b) => $a['median_minutes'] <=> $b['median_minutes']);
        }

        return [
            'morning' => $bySlot[self::SLOT_MORNING],
            'work' => $bySlot[self::SLOT_WORK],
            'afternoon' => $bySlot[self::SLOT_AFTERNOON],
            'evening' => $bySlot[self::SLOT_EVENING],
            'by_task' => $byTask,
        ];
    }

    /**
     * Soft signal: task có phải routine với confidence đủ cao không (để planner boost/ hỏi).
     */
    public function isRoutine(int $taskId, ?float $minConfidence = null): bool
    {
        $minConfidence = $minConfidence ?? (float) $this->config('confidence_threshold', 0.5);
        $r = $this->getRoutineForTask($taskId);

        return $r !== null && $r['confidence'] >= $minConfidence;
    }

    /**
     * Nhãn slot cho UI.
     */
    public static function slotLabel(string $slot): string
    {
        return match ($slot) {
            self::SLOT_MORNING => '🌅 Morning routine',
            self::SLOT_WORK => '📋 Work block',
            self::SLOT_AFTERNOON => '☀️ Afternoon block',
            self::SLOT_EVENING => '🌙 Evening routine',
            default => $slot,
        };
    }
}
