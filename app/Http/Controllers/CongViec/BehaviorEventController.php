<?php

namespace App\Http\Controllers\CongViec;

use App\Http\Controllers\Controller;
use App\Models\BehaviorEvent;
use App\Services\MicroEventCaptureService;
use App\Services\PolicyFeedbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BehaviorEventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($user->behavior_events_consent !== true) {
            return response()->json(['message' => 'Consent required', 'captured' => 0], 200);
        }

        $validated = $request->validate([
            'events' => ['required', 'array', 'max:50'],
            'events.*.event_type' => ['required', 'string', 'max:80'],
            'events.*.work_task_id' => ['nullable', 'integer', 'exists:work_tasks,id'],
            'events.*.payload' => ['nullable', 'array'],
        ]);

        $count = app(MicroEventCaptureService::class)->captureBatch($user->id, $validated['events']);

        foreach ($validated['events'] as $e) {
            if (($e['event_type'] ?? '') === BehaviorEvent::TYPE_POLICY_FEEDBACK && isset($e['payload']) && is_array($e['payload'])) {
                app(PolicyFeedbackService::class)->apply($user->id, $e['payload']);
            }
        }

        return response()->json(['captured' => $count]);
    }
}
