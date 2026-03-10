<?php

namespace App\Services;

use App\Models\CongViecTask;
use App\Models\WorkTaskInstance;
use Carbon\Carbon;

/**
 * Task Energy Model — học từ actual_duration vs estimated_duration: task hoàn thành "tốt" ở slot nào.
 * quality = estimated/actual; gom theo energy slot (night/morning/afternoon/evening) → best_slot = affinity.
 */
class EnergyAffinityService
{
    public const SLOT_NIGHT = 'night';
    public const SLOT_MORNING = 'morning';
    public const SLOT_AFTERNOON = 'afternoon';
    public const SLOT_EVENING = 'evening';
    public const AFFINITY_NEUTRAL = 'neutral';

    /** floor(hour/6): 0=00-06 night, 1=06-12 morning, 2=12-18 afternoon, 3=18-24 evening */
    protected const SLOT_INDEX_TO_NAME = [
        0 => self::SLOT_NIGHT,
        1 => self::SLOT_MORNING,
        2 => self::SLOT_AFTERNOON,
        3 => self::SLOT_EVENING,
    ];

    protected function config(string $key, $default)
    {
        return config('behavior_intelligence.energy_affinity.' . $key, $default);
    }

    protected function hourToSlotIndex(int $hour): int
    {
        return (int) floor($hour / 6) % 4;
    }

    protected function slotIndexToName(int $index): string
    {
        return self::SLOT_INDEX_TO_NAME[$index] ?? self::AFFINITY_NEUTRAL;
    }

    /**
     * Execution quality: estimated/actual. > 1 = nhanh, < 1 = chậm.
     * actual >= 1 để tránh chia 0.
     */
    protected function quality(?int $estimated, ?int $actual): ?float
    {
        if ($actual === null || $actual < 1) {
            return null;
        }
        $est = $estimated ?? $actual;
        if ($est < 1) {
            return null;
        }

        return (float) $est / (float) $actual;
    }

    /**
     * Affinity cho task: từ last N instances, quality theo slot → best slot.
     *
     * @return array{affinity: string, confidence: float, slot_index: int}|null
     */
    public function getAffinityForTask(int $taskId): ?array
    {
        $minSamples = (int) $this->config('min_samples', 6);
        $maxSamples = (int) $this->config('max_samples', 30);
        $task = CongViecTask::find($taskId);
        if (! $task) {
            return null;
        }

        $instances = WorkTaskInstance::where('work_task_id', $taskId)
            ->where('status', WorkTaskInstance::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->limit($maxSamples)
            ->get();

        $estimated = $task->estimated_duration ? (int) $task->estimated_duration : null;
        $rows = [];
        foreach ($instances as $i) {
            $completedAt = $i->completed_at;
            if (! $completedAt) {
                continue;
            }
            $t = $completedAt->copy()->setTimezone('Asia/Ho_Chi_Minh');
            $hour = (int) $t->format('G');
            $actual = $i->actual_duration !== null ? (int) $i->actual_duration : null;
            $est = $estimated ?? $actual;
            $q = $this->quality($est, $actual);
            if ($q === null) {
                continue;
            }
            $rows[] = ['slot_index' => $this->hourToSlotIndex($hour), 'quality' => $q];
        }

        if (count($rows) < $minSamples) {
            return [
                'affinity' => self::AFFINITY_NEUTRAL,
                'confidence' => 0.0,
                'slot_index' => -1,
            ];
        }

        $bySlot = [];
        foreach ($rows as $r) {
            $idx = $r['slot_index'];
            if (! isset($bySlot[$idx])) {
                $bySlot[$idx] = [];
            }
            $bySlot[$idx][] = $r['quality'];
        }
        $avgBySlot = [];
        foreach ($bySlot as $idx => $quals) {
            $avgBySlot[$idx] = array_sum($quals) / count($quals);
        }
        if (empty($avgBySlot)) {
            return [
                'affinity' => self::AFFINITY_NEUTRAL,
                'confidence' => 0.0,
                'slot_index' => -1,
            ];
        }
        $bestIndex = (int) array_search(max($avgBySlot), $avgBySlot);
        $confidence = min(1.0, count($rows) / 15.0);
        $drift = config('behavior_intelligence.enabled', true)
            ? app(BehaviorDriftService::class)->getDriftForTask($taskId)
            : null;
        if ($drift && $drift['drift_detected']) {
            $confidence *= ($drift['routine_decay'] ?? 0.7);
        }

        return [
            'affinity' => $this->slotIndexToName($bestIndex),
            'confidence' => round($confidence, 4),
            'slot_index' => $bestIndex,
        ];
    }

    /**
     * Chỉ số slot hiện tại từ Carbon (0–3).
     */
    public function currentSlotIndex(Carbon $now): int
    {
        $hour = (int) $now->copy()->setTimezone('Asia/Ho_Chi_Minh')->format('G');

        return $this->hourToSlotIndex($hour);
    }

    /**
     * Có nên cộng energy_bonus không: task affinity trùng slot hiện tại và confidence đủ.
     */
    public function matchesCurrentSlot(int $taskId, Carbon $now, float $minConfidence = 0.3): bool
    {
        $a = $this->getAffinityForTask($taskId);
        if (! $a || $a['affinity'] === self::AFFINITY_NEUTRAL || $a['confidence'] < $minConfidence) {
            return false;
        }
        $current = $this->currentSlotIndex($now);

        return $a['slot_index'] === $current;
    }

    /**
     * Cập nhật task.meta (energy_affinity, energy_confidence) — gọi mỗi 5 completion hoặc từ job.
     */
    public function updateTaskMeta(int $taskId): void
    {
        $a = $this->getAffinityForTask($taskId);
        $task = CongViecTask::find($taskId);
        if (! $task) {
            return;
        }
        if (! \Illuminate\Support\Facades\Schema::hasColumn($task->getTable(), 'meta')) {
            return;
        }
        $meta = $task->meta ?? [];
        $meta['energy_affinity'] = $a['affinity'] ?? self::AFFINITY_NEUTRAL;
        $meta['energy_confidence'] = $a['confidence'] ?? 0;
        $task->meta = $meta;
        $task->save();
    }

    public static function affinityLabel(string $affinity): string
    {
        return match ($affinity) {
            self::SLOT_NIGHT => '🌙 Night fit',
            self::SLOT_MORNING => '🌅 Morning fit',
            self::SLOT_AFTERNOON => '☀️ Afternoon fit',
            self::SLOT_EVENING => '🌙 Evening fit',
            default => '',
        };
    }
}
