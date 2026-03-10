<?php

namespace App\Http\Controllers;

use App\Models\BehaviorEvent;
use App\Models\BehaviorProgram;
use App\Models\CongViecTask;
use App\Models\KanbanColumn;
use App\Models\WorkTaskInstance;
use App\Models\Label;
use App\Models\Project;
use App\Services\AdaptiveTrustGradientService;
use App\Services\CongViecPageDataService;
use App\Services\EnsureTaskInstancesService;
use App\Services\MicroEventCaptureService;
use App\Services\DurationSuggestionService;
use App\Services\EnergyAffinityService;
use App\Services\FocusDurationGuardService;
use App\Services\FocusLoadService;
use App\Services\FocusSessionService;
use App\Services\ProbabilisticTruthService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CongViecController extends Controller
{
    /**
     * @return View|Response
     */
    public function index(Request $request)
    {
        try {
            $data = app(CongViecPageDataService::class)->getIndexData($request);
        } catch (\Throwable $e) {
            Log::error('CongViecController@index: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString(),
            ]);
            $data = CongViecPageDataService::getMinimalDataForError($request);
            $data['errorMessage'] = 'Không tải được dữ liệu công việc. Vui lòng thử lại sau.';
        }

        $userId = $request->user()?->id;
        $focusPlan = $data['focusPlan'] ?? null;
        if ($userId && is_array($focusPlan) && ($focusPlan['focus'] ?? null) instanceof \Illuminate\Support\Collection && $focusPlan['focus']->isNotEmpty()) {
            session(['focus_first_instance_id' => $focusPlan['focus']->first()->id]);
        } else {
            session()->forget('focus_first_instance_id');
        }

        if (! empty($data['errorMessage'])) {
            try {
                return view('pages.cong-viec', $data);
            } catch (\Throwable $viewEx) {
                Log::error('CongViecController@index view render: ' . $viewEx->getMessage(), ['trace' => $viewEx->getTraceAsString()]);
                return response()->view('errors.minimal-message', [
                    'message' => $data['errorMessage'],
                    'retryUrl' => route('cong-viec'),
                ], 200);
            }
        }
        if ($request->boolean('partial')) {
            $tab = $request->get('tab');
            $noCache = ['Cache-Control' => 'no-store, no-cache, must-revalidate'];
            if ($tab === 'hom-nay') {
                return response()->view('pages.cong-viec.partials.hom-nay', $data)->header('Content-Type', 'text/html; charset=UTF-8')->withHeaders($noCache);
            }
            if ($tab === 'tong-quan') {
                return response()->view('pages.cong-viec.partials.tong-quan', $data)->header('Content-Type', 'text/html; charset=UTF-8')->withHeaders($noCache);
            }
            if ($tab === 'du-kien') {
                return response()->view('pages.cong-viec.partials.du-kien', $data)->header('Content-Type', 'text/html; charset=UTF-8')->withHeaders($noCache);
            }
            if ($tab === 'hoan-thanh') {
                return response()->view('pages.cong-viec.partials.hoan-thanh', $data)->header('Content-Type', 'text/html; charset=UTF-8')->withHeaders($noCache);
            }
        }

        return view('pages.cong-viec', $data);
    }

    public function fromSchedulePayload(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Vui lòng đăng nhập.'], 401);
        }
        $scheduleId = (int) $request->query('schedule_id');
        if (! $scheduleId) {
            return response()->json(['success' => false, 'message' => 'Thiếu schedule_id.'], 400);
        }
        $schedule = \App\Models\PaymentSchedule::where('user_id', $user->id)->where('id', $scheduleId)->first();
        if (! $schedule) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy lịch thanh toán.'], 404);
        }
        $payload = app(\App\Services\PaymentScheduleToTaskService::class)->buildTaskPayloadFromSchedule($schedule);
        return response()->json(['success' => true, 'payload' => $payload]);
    }

    public function similarTasks(Request $request): JsonResponse
    {
        $request->validate(['title' => ['nullable', 'string', 'max:500']]);
        $title = trim((string) $request->input('title', ''));
        if (strlen($title) < 2) {
            return response()->json(['suggestions' => []]);
        }
        $userId = $request->user()->id;
        $tasks = CongViecTask::where('user_id', $userId)
            ->where('title', 'like', '%' . $title . '%')
            ->orderByDesc('updated_at')
            ->limit(3)
            ->get(['id', 'title', 'due_time', 'repeat', 'estimated_duration']);
        $pattern = app(\App\Services\TaskPatternLearningService::class);
        $suggestions = $tasks->map(function ($t) use ($pattern) {
            $dueTime = $t->due_time;
            if ($dueTime && strlen($dueTime) >= 5) {
                $dueTime = substr($dueTime, 0, 5);
            }
            $preferredTime = $pattern->getPreferredTimeString($t->id);
            return [
                'title' => $t->title,
                'due_time' => $dueTime,
                'preferred_time' => $preferredTime,
                'repeat' => in_array($t->repeat ?? 'none', ['none', 'daily', 'weekly', 'monthly', 'custom']) ? ($t->repeat ?? 'none') : 'none',
                'estimated_duration' => $t->estimated_duration ? (int) $t->estimated_duration : null,
            ];
        })->values()->all();
        return response()->json(['suggestions' => $suggestions]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $userId = $request->user()->id;
        $allowedSlugs = KanbanColumn::where('user_id', $userId)->pluck('slug')->all();
        if ($allowedSlugs === []) {
            $allowedSlugs = array_keys(CongViecTask::KANBAN_STATUSES);
        }
        $request->merge([
            'priority' => $request->input('priority') !== '' && $request->input('priority') !== null ? (int) $request->input('priority') : null,
            'remind_minutes_before' => $request->input('remind_minutes_before') !== '' && $request->input('remind_minutes_before') !== null ? (int) $request->input('remind_minutes_before') : null,
            'project_id' => $request->input('project_id') !== '' && $request->input('project_id') !== null ? (int) $request->input('project_id') : null,
            'program_id' => $request->input('program_id') !== '' && $request->input('program_id') !== null ? (int) $request->input('program_id') : null,
            'task_due_date' => $request->filled('task_due_date') ? $request->input('task_due_date') : null,
            'task_due_time' => $request->filled('task_due_time') ? $request->input('task_due_time') : null,
            'task_repeat_until' => $request->filled('task_repeat_until') ? $request->input('task_repeat_until') : null,
            'task_available_after' => $request->filled('task_available_after') ? $request->input('task_available_after') : null,
            'task_available_before' => $request->filled('task_available_before') ? $request->input('task_available_before') : null,
        ]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'description_html' => ['nullable', 'string'],
            'kanban_status' => ['nullable', 'string', Rule::in($allowedSlugs)],
            'priority' => ['nullable', 'integer', 'in:1,2,3,4'],
            'task_due_date' => ['nullable', 'date'],
            'task_due_time' => ['nullable', 'string', 'max:5', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
            'task_available_after' => ['nullable', 'string', 'max:5', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
            'task_available_before' => ['nullable', 'string', 'max:5', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
            'task_repeat' => ['nullable', 'string', 'in:none,daily,weekly,monthly,custom'],
            'task_repeat_until' => ['nullable', 'date', Rule::when($request->filled('task_due_date'), ['after_or_equal:task_due_date'])],
            'task_repeat_interval' => ['nullable', 'integer', 'min:1', 'max:99'],
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
        if (empty($dueDate) || $dueDate === '') {
            $dueDate = Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d');
        }
        $task->due_date = $dueDate;
        $task->due_time = ! empty($validated['task_due_time']) ? substr($validated['task_due_time'], 0, 5) : null;
        $task->available_after = ! empty($validated['task_available_after']) ? substr($validated['task_available_after'], 0, 5) : null;
        $task->available_before = ! empty($validated['task_available_before']) ? substr($validated['task_available_before'], 0, 5) : null;
        $task->remind_minutes_before = $validated['remind_minutes_before'] ?? null;
        $task->location = $validated['location'] ?? null;
        $task->repeat = $validated['task_repeat'] ?? 'none';
        $task->repeat_until = $validated['task_repeat_until'] ?? null;
        $task->repeat_interval = isset($validated['task_repeat_interval']) && $validated['task_repeat_interval'] >= 1 ? (int) $validated['task_repeat_interval'] : 1;
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

        app(EnsureTaskInstancesService::class)->ensureForUserAndDate(
            $request->user()->id,
            Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d')
        );

        if ($request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json(['success' => true, 'message' => 'Đã thêm công việc.']);
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

    public function editData(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $task = CongViecTask::with('labels')->where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();
        return response()->json([
            'id' => $task->id,
            'title' => $task->title,
            'description_html' => $task->description_html ?? '',
            'due_date' => $task->due_date ? $task->due_date->format('Y-m-d') : null,
            'due_time' => $task->due_time ? substr($task->due_time, 0, 5) : null,
            'repeat' => $task->repeat ?? 'none',
            'repeat_until' => $task->repeat_until ? $task->repeat_until->format('Y-m-d') : null,
            'repeat_interval' => (int) ($task->repeat_interval ?? 1),
            'priority' => $task->priority,
            'remind_minutes_before' => $task->remind_minutes_before,
            'kanban_status' => $task->kanban_status ?? 'backlog',
            'project_id' => $task->project_id,
            'program_id' => $task->program_id,
            'label_ids' => $task->labels->pluck('id')->values()->all(),
            'location' => $task->location ?? '',
            'category' => $task->category ?? '',
            'impact' => $task->impact ?? '',
            'estimated_duration' => (int) ($task->estimated_duration ?? 0),
            'available_after' => $task->available_after ? substr($task->available_after, 0, 5) : null,
            'available_before' => $task->available_before ? substr($task->available_before, 0, 5) : null,
        ]);
    }

    public function show(Request $request, int $id): View
    {
        $task = CongViecTask::with(['labels', 'project', 'program'])
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
        $validTabs = ['tong-quan', 'hom-nay', 'du-kien', 'hoan-thanh'];
        $returnTab = $request->query('tab');
        if (! in_array($returnTab, $validTabs, true)) {
            $returnTab = 'tong-quan';
        }
        $returnUrl = null;
        $returnLabel = 'Quay lại công việc';
        if ($request->query('from') === 'lich-thanh-toan') {
            $returnUrl = route('tai-chinh', ['tab' => 'lich-thanh-toan']);
            $returnLabel = 'Quay lại lịch thanh toán';
        }
        return view('pages.cong-viec.task-detail', [
            'task' => $task,
            'returnTab' => $returnTab,
            'returnUrl' => $returnUrl,
            'returnLabel' => $returnLabel,
        ]);
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
            'task_repeat_until' => $request->filled('task_repeat_until') ? $request->input('task_repeat_until') : null,
            'task_available_after' => $request->filled('task_available_after') ? $request->input('task_available_after') : null,
            'task_available_before' => $request->filled('task_available_before') ? $request->input('task_available_before') : null,
        ]);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'description_html' => ['nullable', 'string'],
            'priority' => ['nullable', 'integer', 'in:1,2,3,4'],
            'remind_minutes_before' => ['nullable', 'integer', 'in:0,5,15,30,60,120,1440'],
            'task_due_date' => ['nullable', 'date'],
            'task_due_time' => ['nullable', 'string', 'max:8', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/'],
            'task_available_after' => ['nullable', 'string', 'max:5', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
            'task_available_before' => ['nullable', 'string', 'max:5', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
            'task_repeat' => ['nullable', 'string', 'in:none,daily,weekly,monthly,custom'],
            'task_repeat_until' => ['nullable', 'date', Rule::when($request->filled('task_due_date'), ['after_or_equal:task_due_date'])],
            'task_repeat_interval' => ['nullable', 'integer', 'min:1', 'max:99'],
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
        $task->available_after = ! empty($validated['task_available_after']) ? substr($validated['task_available_after'], 0, 5) : null;
        $task->available_before = ! empty($validated['task_available_before']) ? substr($validated['task_available_before'], 0, 5) : null;
        $task->location = $validated['location'] ?? null;
        $task->repeat = $validated['task_repeat'] ?? 'none';
        $task->repeat_until = $validated['task_repeat_until'] ?? null;
        $task->repeat_interval = isset($validated['task_repeat_interval']) && $validated['task_repeat_interval'] >= 1 ? (int) $validated['task_repeat_interval'] : 1;
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

    public function destroy(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $task = CongViecTask::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();
        $task->labels()->detach();
        $task->delete();

        if ($request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json(['success' => true, 'message' => 'Đã xoá công việc.']);
        }

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

    public function toggleInstanceComplete(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $instance = WorkTaskInstance::with('task')->whereHas('task', fn ($q) => $q->where('user_id', $user->id))->findOrFail($id);
        $task = $instance->task;

        $payload = [];
        if ($request->has('latency_ms')) {
            $payload['latency_ms'] = (int) $request->input('latency_ms');
        }
        if ($request->filled('deadline_at')) {
            $payload['deadline_at'] = $request->input('deadline_at');
        }

        $tickingToComplete = $instance->status !== WorkTaskInstance::STATUS_COMPLETED;
        if ($tickingToComplete && config('behavior_intelligence.enabled', true)) {
            $truth = app(ProbabilisticTruthService::class)->estimate($user->id, $task->id, $payload ?: null);
            if ($truth['require_confirmation']) {
                return response()->json([
                    'completed' => false,
                    'require_confirmation' => true,
                    'p' => $truth['p'],
                ]);
            }
        }

        $durationConfirm = null;
        if ($instance->status === WorkTaskInstance::STATUS_COMPLETED) {
            $instance->status = WorkTaskInstance::STATUS_PENDING;
            $instance->completed_at = null;
        } else {
            $instance->status = WorkTaskInstance::STATUS_COMPLETED;
            $instance->completed_at = now();
            $durationConfirm = $this->applyFocusDurationIfAny($user->id, $instance);
        }
        $instance->save();

        if ($tickingToComplete) {
            $this->captureTickAndUpdateTrust($user->id, $task->id, $payload, null, $task->program_id);
        }

        $programProgress = CongViecPageDataService::buildProgramProgressPayload($user->id, $task);

        $durationSuggestion = null;
        $breakSuggestion = null;
        if ($tickingToComplete && $instance->status === WorkTaskInstance::STATUS_COMPLETED) {
            $instance->refresh();
            $durationSuggestion = app(DurationSuggestionService::class)->maybeSuggestAfterComplete($task, $instance);
            $breakSuggestion = app(FocusLoadService::class)->maybeSuggestBreak($user->id);
            $this->maybeUpdateEnergyMeta($task->id);
        }

        $json = [
            'completed' => $instance->status === WorkTaskInstance::STATUS_COMPLETED,
            'program_progress' => $programProgress,
        ];
        if ($durationSuggestion !== null) {
            $json['duration_suggestion'] = $durationSuggestion;
        }
        if ($breakSuggestion !== null) {
            $json['break_suggestion'] = $breakSuggestion;
        }
        if ($durationConfirm !== null) {
            $json['duration_confirm'] = $durationConfirm;
        }

        return response()->json($json);
    }

    public function confirmInstanceComplete(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $instance = WorkTaskInstance::with('task')->whereHas('task', fn ($q) => $q->where('user_id', $user->id))->findOrFail($id);
        $task = $instance->task;

        $payload = [];
        if ($request->has('latency_ms')) {
            $payload['latency_ms'] = (int) $request->input('latency_ms');
        }
        if ($request->filled('deadline_at')) {
            $payload['deadline_at'] = $request->input('deadline_at');
        }

        $instance->status = WorkTaskInstance::STATUS_COMPLETED;
        $instance->completed_at = now();
        $durationConfirm = $this->applyFocusDurationIfAny($user->id, $instance);
        $instance->save();
        app(FocusSessionService::class)->stop($user->id);
        $this->captureTickAndUpdateTrust($user->id, $task->id, $payload, 1.0, $task->program_id);

        $programProgress = CongViecPageDataService::buildProgramProgressPayload($user->id, $task);

        $instance->refresh();
        $durationSuggestion = app(DurationSuggestionService::class)->maybeSuggestAfterComplete($task, $instance);
        $breakSuggestion = app(FocusLoadService::class)->maybeSuggestBreak($user->id);
        $this->maybeUpdateEnergyMeta($task->id);

        $json = [
            'completed' => true,
            'program_progress' => $programProgress,
        ];
        if ($durationSuggestion !== null) {
            $json['duration_suggestion'] = $durationSuggestion;
        }
        if ($breakSuggestion !== null) {
            $json['break_suggestion'] = $breakSuggestion;
        }
        if ($durationConfirm !== null) {
            $json['duration_confirm'] = $durationConfirm;
        }

        return response()->json($json);
    }

    public function focusBreakStart(Request $request): JsonResponse
    {
        $user = $request->user();
        app(FocusLoadService::class)->recordBreak($user->id);
        return response()->json(['ok' => true]);
    }

    public function patchEstimatedDuration(Request $request, int $id): JsonResponse
    {
        $task = CongViecTask::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();
        $validated = $request->validate([
            'estimated_duration' => ['required', 'integer', 'min:1', 'max:1440'],
        ]);
        $task->estimated_duration = (int) $validated['estimated_duration'];
        $task->save();

        return response()->json([
            'ok' => true,
            'estimated_duration' => $task->estimated_duration,
        ]);
    }

    public function focusStart(Request $request, int $instance): JsonResponse
    {
        $user = $request->user();
        if ((int) session('focus_first_instance_id') !== $instance) {
            return response()->json(['ok' => false, 'message' => 'Chỉ task đầu focus mới bắt đầu được.'], 422);
        }
        $inst = WorkTaskInstance::with('task')->whereHas('task', fn ($q) => $q->where('user_id', $user->id))->findOrFail($instance);
        if ($inst->status === WorkTaskInstance::STATUS_COMPLETED) {
            return response()->json(['ok' => false, 'message' => 'Đã hoàn thành.'], 422);
        }
        $svc = app(FocusSessionService::class);
        $ghost = null;
        $prev = $svc->get($user->id);
        if ($prev && (int) $prev['instance_id'] !== (int) $inst->id) {
            $prevInst = WorkTaskInstance::with('task')->find($prev['instance_id']);
            if ($prevInst && $prevInst->status !== WorkTaskInstance::STATUS_COMPLETED && $prevInst->task) {
                $started = (int) $prev['started_at'];
                $lastAct = (int) ($prev['last_activity_at'] ?? $started);
                $idleSec = (int) config('behavior_intelligence.focus_duration.idle_seconds', 300);
                $endUnix = (time() - $lastAct > $idleSec) ? $lastAct : time();
                $elapsedMin = max(0, (int) round(($endUnix - $started) / 60));
                $est = $prevInst->task->estimated_duration ? (int) $prevInst->task->estimated_duration : null;
                if ($est && $est > 0 && $elapsedMin >= (int) ceil($est * 0.6)) {
                    $ghost = [
                        'instance_id' => $prevInst->id,
                        'title' => $prevInst->task->title,
                        'elapsed_minutes' => max(1, $elapsedMin),
                    ];
                    $prevInst->ghost_completion_detected = true;
                    $prevInst->save();
                }
            }
            $svc->stop($user->id);
        }
        $svc->start($user->id, $inst->id);
        $now = now();
        $inst->focus_started_at = $now;
        $inst->focus_last_activity_at = $now;
        $inst->focus_stopped_at = null;
        $inst->focus_recorded_minutes = null;
        $inst->save();

        $task = $inst->task;
        $payload = [
            'instance_id' => $inst->id,
            'instance_date' => $inst->instance_date?->format('Y-m-d'),
            'started_at' => now()->toIso8601String(),
            'repeat' => $task->repeat ?? null,
            'category' => $task->category ?? null,
            'kanban_status' => $task->kanban_status ?? null,
            'program_id' => $task->program_id,
            'estimated_duration' => $task->estimated_duration,
            'impact' => $task->impact ?? null,
        ];
        app(MicroEventCaptureService::class)->capture($user->id, BehaviorEvent::TYPE_FOCUS_START, $task->id, $payload);

        $out = [
            'ok' => true,
            'instance_id' => $inst->id,
            'title' => $inst->task->title,
            'started_at' => time(),
        ];
        if ($ghost !== null) {
            $out['ghost_completion'] = $ghost;
        }

        return response()->json($out);
    }

    public function focusActivity(Request $request): JsonResponse
    {
        $user = $request->user();
        $svc = app(FocusSessionService::class);
        $svc->touchActivity($user->id);
        $s = $svc->get($user->id);
        if ($s && ! empty($s['instance_id'])) {
            $inst = WorkTaskInstance::where('id', $s['instance_id'])->whereHas('task', fn ($q) => $q->where('user_id', $user->id))->first();
            if ($inst) {
                $inst->focus_last_activity_at = now();
                $inst->save();
            }
        }

        return response()->json(['ok' => true]);
    }

    public function focusStop(Request $request): JsonResponse
    {
        $user = $request->user();
        $svc = app(FocusSessionService::class);
        $session = $svc->get($user->id);
        if ($session && ! empty($session['instance_id'])) {
            $inst = WorkTaskInstance::with('task')->find($session['instance_id']);
            if ($inst && $inst->task && $inst->task->user_id === $user->id) {
                $guard = app(FocusDurationGuardService::class);
                $resolved = $guard->resolveForComplete(
                    $inst,
                    $inst->task,
                    (int) $session['started_at'],
                    isset($session['last_activity_at']) ? (int) $session['last_activity_at'] : null,
                    time()
                );
                if ($resolved['minutes'] !== null) {
                    $inst->focus_stopped_at = now();
                    $inst->focus_recorded_minutes = $resolved['minutes'];
                    $inst->save();
                }
                $elapsed = $svc->elapsedSeconds($user->id);
                $payload = [
                    'instance_id' => $inst->id,
                    'stopped_at' => now()->toIso8601String(),
                    'elapsed_seconds' => $elapsed,
                    'reason' => 'switch_task',
                    'repeat' => $inst->task->repeat ?? null,
                    'category' => $inst->task->category ?? null,
                    'kanban_status' => $inst->task->kanban_status ?? null,
                    'program_id' => $inst->task->program_id,
                ];
                app(MicroEventCaptureService::class)->capture($user->id, BehaviorEvent::TYPE_FOCUS_STOP, $inst->task->id, $payload);
            }
        }
        $svc->stop($user->id);

        return response()->json(['ok' => true]);
    }

    /**
     * Không set actual_duration nếu không có focus session khớp instance.
     * Dùng idle + cap + sanity; trả duration_confirm để toast chọn phút.
     *
     * @return array|null
     */
    protected function applyFocusDurationIfAny(int $userId, WorkTaskInstance $instance): ?array
    {
        $svc = app(FocusSessionService::class);
        $s = $svc->get($userId);
        if (! $s || (int) $s['instance_id'] !== (int) $instance->id) {
            return null;
        }
        $task = $instance->task;
        $stoppedUnix = $instance->focus_stopped_at ? $instance->focus_stopped_at->timestamp : null;
        $guard = app(FocusDurationGuardService::class);
        $resolved = $guard->resolveForComplete(
            $instance,
            $task,
            (int) $s['started_at'],
            isset($s['last_activity_at']) ? (int) $s['last_activity_at'] : null,
            $stoppedUnix
        );
        if ($resolved['need_short_pick'] || $resolved['need_sanity_pick']) {
            $svc->stop($userId);
            return [
                'instance_id' => $instance->id,
                'kind' => $resolved['need_short_pick'] ? 'short' : 'sanity',
                'message' => $resolved['need_short_pick']
                    ? 'Bạn mất khoảng bao lâu? (có thể đã xong trước khi bấm bắt đầu)'
                    : sprintf('Bạn thực sự mất %d phút cho task này?', $resolved['raw_elapsed_minutes']),
                'options' => $resolved['options'],
                'raw_minutes' => $resolved['raw_elapsed_minutes'],
            ];
        }
        if ($resolved['minutes'] !== null && $resolved['use_for_learning']) {
            $instance->actual_duration = $resolved['minutes'];
            $instance->focus_recorded_minutes = $resolved['minutes'];
            $instance->focus_stopped_at = $instance->focus_stopped_at ?? now();
        }
        $svc->stop($userId);

        return null;
    }

    public function patchInstanceActualDuration(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $instance = WorkTaskInstance::with('task')->whereHas('task', fn ($q) => $q->where('user_id', $user->id))->findOrFail($id);
        $validated = $request->validate([
            'actual_duration' => ['required', 'integer', 'min:1', 'max:1440'],
            'ghost_confirm' => ['sometimes', 'boolean'],
        ]);
        $instance->actual_duration = (int) $validated['actual_duration'];
        $instance->focus_recorded_minutes = (int) $validated['actual_duration'];
        if (! empty($validated['ghost_confirm'])) {
            $instance->ghost_completion_confirmed = true;
            if ($instance->status !== WorkTaskInstance::STATUS_COMPLETED) {
                $instance->status = WorkTaskInstance::STATUS_COMPLETED;
                $instance->completed_at = now();
            }
        }
        $instance->save();
        app(FocusSessionService::class)->stop($user->id);

        return response()->json(['ok' => true, 'actual_duration' => $instance->actual_duration]);
    }

    /** Cập nhật work_tasks.meta (energy_affinity) mỗi 5 lần hoàn thành để tránh noise. */
    protected function maybeUpdateEnergyMeta(int $taskId): void
    {
        if (! config('behavior_intelligence.enabled', true)) {
            return;
        }
        $everyN = (int) config('behavior_intelligence.energy_affinity.update_meta_every_n_completions', 5);
        $count = WorkTaskInstance::where('work_task_id', $taskId)
            ->where('status', WorkTaskInstance::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->count();
        if ($everyN > 0 && $count > 0 && $count % $everyN === 0) {
            app(EnergyAffinityService::class)->updateTaskMeta($taskId);
        }
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
