<?php

namespace App\Services;

use App\Models\BehaviorEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MicroEventCaptureService
{
    protected int $batchMax;

    public function __construct()
    {
        $this->batchMax = (int) config('behavior_intelligence.micro_event.batch_max', 50);
    }

    /**
     * Nhận batch event, chuẩn hóa và lưu. Trả về số đã lưu.
     *
     * @param  array<int, array{event_type: string, work_task_id?: int, payload?: array}>  $events
     */
    public function captureBatch(int $userId, array $events): int
    {
        if (! config('behavior_intelligence.layers.micro_event_capture', true)) {
            return 0;
        }

        $allowed = BehaviorEvent::allowedEventTypes();
        $toInsert = [];
        $now = now();

        foreach (array_slice($events, 0, $this->batchMax) as $e) {
            $type = $e['event_type'] ?? '';
            if (! in_array($type, $allowed, true)) {
                continue;
            }
            $workTaskId = isset($e['work_task_id']) ? (int) $e['work_task_id'] : null;
            $payload = isset($e['payload']) && is_array($e['payload']) ? $e['payload'] : null;
            $toInsert[] = [
                'user_id' => $userId,
                'event_type' => $type,
                'work_task_id' => $workTaskId,
                'payload' => $payload ? json_encode($payload) : null,
                'created_at' => $now,
            ];
        }

        if (empty($toInsert)) {
            return 0;
        }

        try {
            DB::table('behavior_events')->insert($toInsert);
        } catch (\Throwable $e) {
            Log::warning('MicroEventCaptureService captureBatch failed: ' . $e->getMessage(), ['user_id' => $userId]);

            return 0;
        }

        return count($toInsert);
    }

    /**
     * Lưu một event đơn.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function capture(int $userId, string $eventType, ?int $workTaskId = null, ?array $payload = null): bool
    {
        return $this->captureBatch($userId, [
            ['event_type' => $eventType, 'work_task_id' => $workTaskId, 'payload' => $payload],
        ]) === 1;
    }
}
