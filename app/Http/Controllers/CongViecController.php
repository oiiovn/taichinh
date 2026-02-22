<?php

namespace App\Http\Controllers;

use App\Models\BehaviorEvent;
use App\Models\BehaviorProgram;
use App\Models\CongViecTask;
use App\Models\KanbanColumn;
use App\Models\Label;
use App\Models\Project;
use App\Services\AdaptiveTrustGradientService;
use App\Services\CongViecPageDataService;
use App\Services\MicroEventCaptureService;
use App\Services\ProbabilisticTruthService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CongViecController extends Controller
{
    public function index(Request $request): View
    {
        $data = app(CongViecPageDataService::class)->getIndexData($request);

        return view('pages.cong-viec', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = $request->user()->id;
        $allowedSlugs = KanbanColumn::where('user_id', $userId)->pluck('slug')->all();
        $request->merge([
            'priority' => $request->input('priority') !== '' && $request->input('priority') !== null ? (int) $request->input('priority') : null,
            'remind_minutes_before' => $request->input('remind_minutes_before') !== '' && $request->input('remind_minutes_before') !== null ? (int) $request->input('remind_minutes_before') : null,
            'project_id' => $request->input('project_id') !== '' && $request->input('project_id') !== null ? (int) $request->input('project_id') : null,
            'program_id' => $request->input('program_id') !== '' && $request->input('program_id') !== null ? (int) $request->input('program_id') : null,
            'task_due_date' => $request->filled('task_due_date') ? $request->input('task_due_date') : null,
            'task_due_time' => $request->filled('task_due_time') ? $request->input('task_due_time') : null,
            'task_repeat_until' => $request->filled('task_repeat_until') ? $request->input('task_repeat_until') : null,
        ]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'description_html' => ['nullable', 'string'],
            'kanban_status' => ['nullable', 'string', Rule::in($allowedSlugs)],
            'priority' => ['nullable', 'integer', 'in:1,2,3,4'],
            'task_due_date' => ['nullable', 'date'],
            'task_due_time' => ['nullable', 'string', 'max:5', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
            'task_repeat' => ['nullable', 'string', 'in:none,daily,weekly,monthly,custom'],
            'task_repeat_until' => ['nullable', 'date', Rule::when($request->filled('task_due_date'), ['after_or_equal:task_due_date'])],
            'remind_minutes_before' => ['nullable', 'integer', 'in:0,5,15,30,60,120,1440'],
            'location' => ['nullable', 'string', 'max:500'],
            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['integer', Rule::exists('labels', 'id')->where('user_id', $userId)],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('user_id', $userId)],
            'program_id' => ['nullable', 'integer', Rule::exists('behavior_programs', 'id')->where('user_id', $userId)],
            'category' => ['nullable', 'string', 'in:revenue,growth,maintenance'],
            'estimated_duration' => ['nullable', 'integer', 'min:0'],
            'impact' => ['nullable', 'string', 'in:high,medium,low'],
        ]);

        $task = new CongViecTask();
        $task->user_id = $request->user()->id;
        $task->project_id = $validated['project_id'] ?? null;
        $task->program_id = $validated['program_id'] ?? null;
        $task->title = $validated['title'];
        $task->description_html = $validated['description_html'] ?? null;
        $task->priority = $validated['priority'] ?? null;
        $dueDate = $validated['task_due_date'] ?? null;
        if (($validated['program_id'] ?? null) && (empty($dueDate) || $dueDate === '')) {
            $dueDate = Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d');
        }
        $task->due_date = $dueDate;
        $task->due_time = ! empty($validated['task_due_time']) ? substr($validated['task_due_time'], 0, 5) : null;
        $task->remind_minutes_before = $validated['remind_minutes_before'] ?? null;
        $task->location = $validated['location'] ?? null;
        $task->repeat = $validated['task_repeat'] ?? 'none';
        $task->repeat_until = $validated['task_repeat_until'] ?? null;
        $task->kanban_status = $validated['kanban_status'] ?? 'backlog';
        $task->category = $validated['category'] ?? null;
        $task->estimated_duration = $validated['estimated_duration'] ?? null;
        $task->impact = $validated['impact'] ?? null;
        $task->save();

        $labelIds = $validated['label_ids'] ?? [];
        if (! empty($labelIds)) {
            $task->labels()->sync(array_map('intval', $labelIds));
        } else {
            $task->labels()->detach();
        }

        return redirect()->route('cong-viec')->with('success', 'Đã thêm công việc.');
    }

    public function storeLabel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);
        $label = new Label();
        $label->user_id = $request->user()->id;
        $label->name = $validated['name'];
        $label->color = $validated['color'] ?? '#6b7280';
        $label->save();
        return response()->json(['id' => $label->id, 'name' => $label->name, 'color' => $label->color]);
    }

    public function storeProject(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);
        $project = new Project();
        $project->user_id = $request->user()->id;
        $project->name = $validated['name'];
        $project->color = $validated['color'] ?? '#6b7280';
        $project->save();
        return response()->json(['id' => $project->id, 'name' => $project->name, 'color' => $project->color]);
    }

    public function edit(Request $request, int $id): RedirectResponse
    {
        $task = CongViecTask::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();
        return redirect()->route('cong-viec', ['edit' => $id]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $task = CongViecTask::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();
        $userId = $request->user()->id;
        $dueTime = $request->input('task_due_time');
        if ($dueTime && strlen($dueTime) === 8 && preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $dueTime)) {
            $dueTime = substr($dueTime, 0, 5);
        }
        $request->merge([
            'priority' => $request->input('priority') !== '' && $request->input('priority') !== null ? (int) $request->input('priority') : null,
            'remind_minutes_before' => $request->input('remind_minutes_before') !== '' && $request->input('remind_minutes_before') !== null ? (int) $request->input('remind_minutes_before') : null,
            'project_id' => $request->input('project_id') !== '' && $request->input('project_id') !== null ? (int) $request->input('project_id') : null,
            'program_id' => $request->input('program_id') !== '' && $request->input('program_id') !== null ? (int) $request->input('program_id') : null,
            'task_due_date' => $request->filled('task_due_date') ? $request->input('task_due_date') : null,
            'task_due_time' => $request->filled('task_due_time') ? $dueTime : null,
        ]);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'description_html' => ['nullable', 'string'],
            'priority' => ['nullable', 'integer', 'in:1,2,3,4'],
            'remind_minutes_before' => ['nullable', 'integer', 'in:0,5,15,30,60,120,1440'],
            'task_due_date' => ['nullable', 'date'],
            'task_due_time' => ['nullable', 'string', 'max:8', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/'],
            'location' => ['nullable', 'string', 'max:500'],
            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['integer', Rule::exists('labels', 'id')->where('user_id', $userId)],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('user_id', $userId)],
            'program_id' => ['nullable', 'integer', Rule::exists('behavior_programs', 'id')->where('user_id', $userId)],
            'category' => ['nullable', 'string', 'in:revenue,growth,maintenance'],
            'estimated_duration' => ['nullable', 'integer', 'min:0'],
            'impact' => ['nullable', 'string', 'in:high,medium,low'],
        ]);
        $task->title = $validated['title'];
        $task->description_html = $validated['description_html'] ?? null;
        $task->priority = $validated['priority'] ?? null;
        $task->remind_minutes_before = isset($validated['remind_minutes_before']) ? (int) $validated['remind_minutes_before'] : null;
        $task->due_date = $validated['task_due_date'] ?? (($validated['program_id'] ?? null) ? Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d') : $task->due_date);
        $task->due_time = ! empty($validated['task_due_time']) ? substr($validated['task_due_time'], 0, 5) : null;
        $task->location = $validated['location'] ?? null;
        $task->category = $validated['category'] ?? null;
        $task->estimated_duration = $validated['estimated_duration'] ?? null;
        $task->impact = $validated['impact'] ?? null;
        $task->project_id = $validated['project_id'] ?? null;
        $task->program_id = $validated['program_id'] ?? null;
        $task->save();
        $labelIds = $validated['label_ids'] ?? [];
        $task->labels()->sync(! empty($labelIds) ? array_map('intval', $labelIds) : []);
        return redirect()->route('cong-viec')->with('success', 'Đã cập nhật công việc.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $task = CongViecTask::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();
        $task->labels()->detach();
        $task->delete();
        return redirect()->route('cong-viec')->with('success', 'Đã xoá công việc.');
    }

    public function toggleComplete(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $task = CongViecTask::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        $payload = [];
        if ($request->has('latency_ms')) {
            $payload['latency_ms'] = (int) $request->input('latency_ms');
        }
        if ($request->filled('deadline_at')) {
            $payload['deadline_at'] = $request->input('deadline_at');
        }

        $tickingToComplete = ! $task->completed;
        if ($tickingToComplete && config('behavior_intelligence.enabled', true)) {
            $truth = app(ProbabilisticTruthService::class)->estimate($user->id, (int) $id, $payload ?: null);
            if ($truth['require_confirmation']) {
                return response()->json([
                    'completed' => false,
                    'require_confirmation' => true,
                    'p' => $truth['p'],
                ]);
            }
        }

        $task->completed = ! $task->completed;
        $task->save();

        if ($tickingToComplete) {
            $this->captureTickAndUpdateTrust($user->id, (int) $id, $payload, null, $task->program_id);
        }

        $programProgress = CongViecPageDataService::buildProgramProgressPayload($user->id, $task);

        return response()->json([
            'completed' => (bool) $task->completed,
            'program_progress' => $programProgress,
        ]);
    }

    public function confirmComplete(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $task = CongViecTask::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $payload = [];
        if ($request->has('latency_ms')) {
            $payload['latency_ms'] = (int) $request->input('latency_ms');
        }
        if ($request->filled('deadline_at')) {
            $payload['deadline_at'] = $request->input('deadline_at');
        }

        $task->completed = true;
        $task->save();
        $this->captureTickAndUpdateTrust($user->id, (int) $id, $payload, 1.0, $task->program_id);

        $programProgress = CongViecPageDataService::buildProgramProgressPayload($user->id, $task);

        return response()->json([
            'completed' => true,
            'program_progress' => $programProgress,
        ]);
    }

    protected function captureTickAndUpdateTrust(int $userId, int $workTaskId, array $payload = [], ?float $pReal = null, ?int $programId = null): void
    {
        $payload['ticked_at'] = now()->toIso8601String();
        app(MicroEventCaptureService::class)->capture($userId, BehaviorEvent::TYPE_TASK_TICK_AT, $workTaskId, $payload);

        if ($pReal === null && config('behavior_intelligence.layers.probabilistic_truth', true)) {
            $truth = app(ProbabilisticTruthService::class)->estimate($userId, $workTaskId);
            $pReal = $truth['p'];
        }

        $trust = app(AdaptiveTrustGradientService::class);
        $completionRate = $trust->getRecentCompletionRate($userId, 7, null);
        $extra = $trust->getLatestVarianceAndRecovery($userId, null);
        $trust->update(
            $userId,
            $pReal,
            $completionRate,
            $extra['variance_score'],
            $extra['recovery_days'],
            null
        );
        if ($programId !== null) {
            $progRate = $trust->getRecentCompletionRate($userId, 7, $programId);
            $progExtra = $trust->getLatestVarianceAndRecovery($userId, $programId);
            $trust->update($userId, $pReal, $progRate, $progExtra['variance_score'], $progExtra['recovery_days'], $programId);
        }
    }

    public function updateKanbanStatus(Request $request, int $id): JsonResponse
    {
        $task = CongViecTask::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();
        $allowedSlugs = KanbanColumn::where('user_id', $request->user()->id)->pluck('slug')->all();
        $validated = $request->validate([
            'kanban_status' => ['required', 'string', Rule::in($allowedSlugs)],
            'actual_duration' => ['nullable', 'integer', 'min:0'],
        ]);
        $newStatus = $validated['kanban_status'];
        if ($newStatus === 'done' && $task->kanban_status !== 'done') {
            $actual = $validated['actual_duration'] ?? $task->actual_duration;
            if ($actual === null || $actual === '') {
                return response()->json([
                    'message' => 'Cần nhập giờ thực tế trước khi chuyển sang Done.',
                    'require_actual_duration' => true,
                ], 422);
            }
            $task->actual_duration = (int) $actual;
        }
        $task->kanban_status = $newStatus;
        $task->completed = ($newStatus === 'done');
        $task->save();
        return response()->json([
            'kanban_status' => $task->kanban_status,
            'completed' => $task->completed,
            'actual_duration' => $task->actual_duration,
        ]);
    }

    public function storeKanbanColumn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
        ]);
        $userId = $request->user()->id;
        $maxPosition = KanbanColumn::where('user_id', $userId)->max('position') ?? -1;
        $nextNum = 1;
        while (KanbanColumn::where('user_id', $userId)->where('slug', 'custom_' . $nextNum)->exists()) {
            $nextNum++;
        }
        $col = KanbanColumn::create([
            'user_id' => $userId,
            'slug' => 'custom_' . $nextNum,
            'label' => $validated['label'],
            'position' => $maxPosition + 1,
        ]);
        return response()->json(['id' => $col->id, 'slug' => $col->slug, 'label' => $col->label, 'position' => $col->position]);
    }

    public function updateKanbanColumn(Request $request, int $id): JsonResponse
    {
        $col = KanbanColumn::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
        ]);
        $col->label = $validated['label'];
        $col->save();
        return response()->json(['id' => $col->id, 'slug' => $col->slug, 'label' => $col->label]);
    }
}
