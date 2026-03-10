<?php

namespace App\Services;

use App\Models\BehaviorProgram;
use App\Models\CongViecTask;
use App\Models\KanbanColumn;
use App\Models\Label;
use App\Models\Project;
use App\Models\WorkTaskInstance;
use App\Services\FocusSessionService;
use App\Services\LongTermProjectionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CongViecPageDataService
{
    public function getIndexData(Request $request): array
    {
        $todayHcm = Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d');
        $userId = $request->user()?->id;

        if ($userId) {
            app(EnsureTaskInstancesService::class)->ensureForUserAndDate($userId, $todayHcm);
        }
        $tasksToday = $this->getTasksToday($userId, $todayHcm);
        $taskStreaks = $this->getTaskStreaksForInstances($userId, $tasksToday);
        $tasksUpcoming = $this->getInstancesUpcoming($userId, $todayHcm);
        $tasksInbox = $this->getTasksInbox($userId);
        $completedInstancesGrouped = $this->getCompletedInstancesGrouped($userId);
        [$kanbanColumns, $kanbanTasks] = $this->getKanbanData($userId);

        $userLabels = $request->user() ? Label::where('user_id', $request->user()->id)->orderBy('name')->get() : collect();
        $userProjects = $request->user() ? Project::where('user_id', $request->user()->id)->orderBy('name')->get() : collect();
        $userPrograms = $request->user() ? BehaviorProgram::where('user_id', $request->user()->id)->where('status', BehaviorProgram::STATUS_ACTIVE)->orderBy('title')->get() : collect();
        $activeProgram = $userId && $userPrograms->isNotEmpty() ? $userPrograms->first() : null;

        $execution = $userId
            ? app(ExecutionEngineService::class)->run($userId, $todayHcm, $tasksToday, $taskStreaks, $activeProgram?->id ?? null)
            : null;

        $behaviorProfile = $execution['behavior_profile'] ?? null;
        $failureDetection = $execution['failure_detection'] ?? null;
        $todayPriority = $execution['today_priority'] ?? ['sorted' => $tasksToday, 'tiers' => ['high' => collect(), 'medium' => collect(), 'low' => collect()], 'scores' => []];
        $focusPlan = $execution['focus_plan'] ?? ['focus' => collect(), 'secondary' => collect(), 'backlog' => collect(), 'total_planned_minutes' => 0, 'available_minutes' => 120];
        $executionMetrics = $execution['execution_metrics'] ?? null;

        $activeProgramProgress = null;
        $todayProgramTaskTotal = 0;
        $todayProgramTaskDone = 0;
        if ($activeProgram && $userId) {
            $activeProgramProgress = app(BehaviorProgramProgressService::class)->getProgressForUi($userId, $activeProgram->id);
            $todayInstancesForProgram = WorkTaskInstance::where('instance_date', $todayHcm)
                ->whereHas('task', fn ($q) => $q->where('user_id', $userId)->where('program_id', $activeProgram->id))
                ->get();
            $todayProgramTaskTotal = $todayInstancesForProgram->count();
            $todayProgramTaskDone = $todayInstancesForProgram->where('status', WorkTaskInstance::STATUS_COMPLETED)->count();
        }

        $behaviorRadar = $this->getBehaviorRadar($userId);
        $editTask = $this->getEditTask($request, $userId);
        $behaviorPolicy = $userId && config('behavior_intelligence.enabled', true)
            ? DB::table('behavior_user_policy')->where('user_id', $userId)->first() : null;
        $behaviorProjection = $userId && config('behavior_intelligence.enabled', true)
            ? app(LongTermProjectionService::class)->getOrCompute($userId) : null;

        $interfaceAdaptation = app(InterfaceAdaptationService::class)->getAdaptation(
            $userId,
            $behaviorRadar ?? [],
            $activeProgram,
            $activeProgramProgress,
            $userPrograms->count()
        );

        $insightPayload = app(ExecutionInsightPayloadService::class)->build(
            $behaviorProfile,
            $failureDetection,
            $todayPriority['sorted']->count(),
            $todayProgramTaskTotal,
            $todayProgramTaskDone,
            $activeProgram,
            $activeProgramProgress,
            $behaviorRadar ?? [],
            $behaviorProjection,
            $interfaceAdaptation
        );
        $coachingNarrative = app(CoachingNarrativeService::class)->generateFromPayload(
            $insightPayload,
            $userId,
            $activeProgram,
            $behaviorProjection
        );

        $this->logCoachingInterventions(
            $userId,
            $behaviorPolicy,
            $interfaceAdaptation,
            $coachingNarrative,
            $behaviorProjection
        );

        $taskCreationContext = $userId
            ? app(TaskCreationContextService::class)->build(
                $focusPlan,
                $executionMetrics,
                $behaviorProfile,
                $failureDetection,
                $insightPayload
            )
            : ['focus_window' => '—', 'workload_pct' => 0, 'suggested_priority' => null, 'suggested_priority_value' => null, 'best_time' => null, 'execution_stage' => 'planning', 'risk_tier' => 'normal', 'overload_hint' => null, 'capacity_remaining_minutes' => 120, 'task_fit_score' => 50];

        $focusSessionPayload = null;
        if ($userId) {
            $fs = app(FocusSessionService::class)->get($userId);
            if ($fs && ! empty($fs['instance_id'])) {
                $fi = WorkTaskInstance::with('task')->find($fs['instance_id']);
                $focusSessionPayload = [
                    'instance_id' => (int) $fs['instance_id'],
                    'started_at' => (int) $fs['started_at'],
                    'title' => $fi?->task?->title ?? '',
                ];
            }
        }

        return [
            'focusSession' => $focusSessionPayload,
            'tasksToday' => $todayPriority['sorted'],
            'tasksTodayTiers' => $todayPriority['tiers'],
            'todayPriorityScores' => $todayPriority['scores'],
            'focusPlan' => $focusPlan,
            'taskStreaks' => $taskStreaks,
            'tasksUpcoming' => $tasksUpcoming,
            'tasksInbox' => $tasksInbox,
            'completedInstancesGrouped' => $completedInstancesGrouped,
            'kanbanColumns' => $kanbanColumns,
            'kanbanTasks' => $kanbanTasks,
            'todayHcm' => $todayHcm,
            'userLabels' => $userLabels,
            'userProjects' => $userProjects,
            'userPrograms' => $userPrograms,
            'activeProgram' => $activeProgram,
            'activeProgramProgress' => $activeProgramProgress,
            'todayProgramTaskTotal' => $todayProgramTaskTotal,
            'todayProgramTaskDone' => $todayProgramTaskDone,
            'behaviorRadar' => $behaviorRadar,
            'editTask' => $editTask,
            'behaviorPolicy' => $behaviorPolicy,
            'behaviorProjection' => $behaviorProjection,
            'interfaceAdaptation' => $interfaceAdaptation,
            'coachingNarrative' => $coachingNarrative,
            'behaviorProfile' => $behaviorProfile,
            'failureDetection' => $failureDetection,
            'insightPayload' => $insightPayload,
            'executionMetrics' => $executionMetrics,
            'taskCreationContext' => $taskCreationContext,
        ];
    }

    protected function logCoachingInterventions(
        ?int $userId,
        $behaviorPolicy,
        array $interfaceAdaptation,
        array $coachingNarrative,
        ?array $behaviorProjection
    ): void {
        if (! $userId || ! config('behavior_intelligence.enabled', true) || ! config('behavior_intelligence.coaching_effectiveness.enabled', true)) {
            return;
        }
        $logger = app(CoachingInterventionLogger::class);
        $ctx = ['stage' => $interfaceAdaptation['stage'] ?? null, 'layout' => $interfaceAdaptation['layout'] ?? null];

        if ($behaviorPolicy && in_array($behaviorPolicy->mode ?? '', ['micro_goal', 'reduced_reminder'], true)) {
            $logger->logIfNotAlreadyToday(
                $userId,
                $behaviorPolicy->mode === 'micro_goal'
                    ? CoachingInterventionLogger::TYPE_POLICY_BANNER_MICRO_GOAL
                    : CoachingInterventionLogger::TYPE_POLICY_BANNER_REDUCED_REMINDER,
                array_merge($ctx, ['mode' => $behaviorPolicy->mode])
            );
        }
        if (! empty($interfaceAdaptation['level_up_message'])) {
            $logger->logIfNotAlreadyToday($userId, CoachingInterventionLogger::TYPE_LEVEL_UP_MESSAGE, $ctx);
        }
        if (! empty($coachingNarrative['today_message'])) {
            $logger->logIfNotAlreadyToday($userId, CoachingInterventionLogger::TYPE_TODAY_MESSAGE, $ctx);
        }
        if (isset($behaviorProjection['suggestion']) && (string) $behaviorProjection['suggestion'] !== '') {
            $logger->logIfNotAlreadyToday($userId, CoachingInterventionLogger::TYPE_INSIGHT_BLOCK, $ctx);
        }
    }

    /**
     * Hôm nay: instance theo ngày (đã ensure), chỉ lấy pending/skipped.
     */
    protected function getTasksToday(?int $userId, string $todayHcm): Collection
    {
        if (! $userId) {
            return collect();
        }
        return WorkTaskInstance::where('instance_date', $todayHcm)
            ->whereHas('task', fn ($q) => $q->where('user_id', $userId))
            ->whereIn('status', [WorkTaskInstance::STATUS_PENDING, WorkTaskInstance::STATUS_SKIPPED])
            ->with(['task.project', 'task.labels', 'task.program'])
            ->orderBy('work_task_id')
            ->get();
    }

    /**
     * Map work_task_id => streak cho các task trong danh sách instances (vd. Hôm nay).
     */
    protected function getTaskStreaksForInstances(?int $userId, Collection $instances): array
    {
        if (! $userId || $instances->isEmpty()) {
            return [];
        }
        $taskIds = $instances->pluck('work_task_id')->unique()->values()->all();
        $streakService = app(TaskStreakService::class);
        $todayHcm = Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d');
        $map = [];
        foreach ($taskIds as $taskId) {
            $result = $streakService->getStreakForTask($userId, (int) $taskId, $todayHcm);
            if ($result['streak'] > 0) {
                $map[(int) $taskId] = $result['streak'];
            }
        }
        return $map;
    }

    /**
     * Dự kiến theo instance: instance_date > hôm nay, pending.
     * Task lặp → chỉ 1 dòng (lần gần nhất) + metadata số lần còn lại / mở rộng.
     */
    protected function getInstancesUpcoming(?int $userId, string $todayHcm): Collection
    {
        if (! $userId) {
            return collect();
        }
        $tomorrow = Carbon::parse($todayHcm)->addDay()->format('Y-m-d');
        $end = Carbon::parse($todayHcm)->addDays((int) config('behavior_intelligence.instance_ensure_horizon_days', 90))->format('Y-m-d');
        $ensure = app(EnsureTaskInstancesService::class);
        $ensure->ensureForUserDateRange($userId, $tomorrow, $end);
        // Việc một lần: luôn tạo instance đúng ngày due (vd. 5/5) để tab Dự kiến có dòng
        $ensure->ensureOneOffDueDatesAhead($userId, $todayHcm);

        $all = WorkTaskInstance::query()
            ->where('instance_date', '>', $todayHcm)
            ->where('status', WorkTaskInstance::STATUS_PENDING)
            ->whereHas('task', fn ($q) => $q->where('user_id', $userId)->where('completed', false))
            ->with(['task.project', 'task.labels', 'task.program'])
            ->orderBy('instance_date')
            ->orderBy('work_task_id')
            ->get();

        $expandLimit = (int) config('behavior_intelligence.du_kien_expand_limit', 10);
        $rows = collect();
        foreach ($all->groupBy('work_task_id') as $taskId => $instances) {
            $sorted = $instances->sortBy(fn ($i) => $i->instance_date->format('Y-m-d'))->values();
            $first = $sorted->first();
            $task = $first->task;
            $repeat = $task ? ($task->repeat ?? 'none') : 'none';
            if ($repeat === 'none' || $repeat === 'custom') {
                foreach ($sorted as $inst) {
                    $rows->push(['kind' => 'single', 'instance' => $inst]);
                }
                continue;
            }
            $more = $sorted->skip(1)->take($expandLimit)->map(fn ($i) => [
                'id' => $i->id,
                'date' => $i->instance_date->format('d/m/Y'),
                'time' => $task->due_time ? substr($task->due_time, 0, 5) : null,
            ])->values()->all();
            $lastInst = $sorted->last();
            $lastDate = $lastInst && $lastInst->instance_date
                ? Carbon::parse($lastInst->instance_date)->format('Y-m-d')
                : null;
            $repeatUntil = $task->repeat_until ? Carbon::parse($task->repeat_until)->format('Y-m-d') : null;
            $horizonUntil = $lastDate;
            if ($repeatUntil && $lastDate) {
                $horizonUntil = min($lastDate, $repeatUntil);
            } elseif ($repeatUntil) {
                $horizonUntil = $repeatUntil;
            }
            $rows->push([
                'kind' => 'recurring',
                'instance' => $first,
                'upcoming_total' => $sorted->count(),
                'more' => $more,
                'more_count' => max(0, $sorted->count() - 1),
                'horizon_until' => $horizonUntil ? Carbon::parse($horizonUntil)->format('d/m/Y') : null,
            ]);
        }

        // Một timeline: sort theo ngày rồi giờ (next execution), không tách lặp/không lặp
        $sorted = $rows->sortBy(function ($r) {
            $inst = $r['instance'];
            $d = $inst->instance_date instanceof \Carbon\Carbon
                ? $inst->instance_date->format('Y-m-d')
                : (string) $inst->instance_date;
            $time = $inst->task->due_time ?? '99:99:99';
            return $d . ' ' . substr($time, 0, 8);
        })->values();

        return $sorted->groupBy(function ($r) {
            $inst = $r['instance'];
            return $inst->instance_date instanceof \Carbon\Carbon
                ? $inst->instance_date->format('Y-m-d')
                : Carbon::parse($inst->instance_date)->format('Y-m-d');
        });
    }

    protected function getTasksInbox(?int $userId): Collection
    {
        if (! $userId) {
            return collect();
        }

        return CongViecTask::where('user_id', $userId)->where('completed', false)
            ->with(['project', 'labels', 'program'])
            ->orderByRaw('due_date IS NULL, due_date ASC, due_time IS NULL, due_time ASC')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Tab Hoàn thành: instance-based, group theo Hôm nay / Hôm qua / Tuần này / Trước đó.
     *
     * @return array{today: \Illuminate\Support\Collection, yesterday: \Illuminate\Support\Collection, this_week: \Illuminate\Support\Collection, older: \Illuminate\Support\Collection}
     */
    protected function getCompletedInstancesGrouped(?int $userId): array
    {
        if (! $userId) {
            return ['today' => collect(), 'yesterday' => collect(), 'this_week' => collect(), 'older' => collect()];
        }

        $today = Carbon::now('Asia/Ho_Chi_Minh')->startOfDay();
        $todayStr = $today->format('Y-m-d');
        $yesterdayStr = $today->copy()->subDay()->format('Y-m-d');
        $startOfWeek = $today->copy()->startOfWeek(Carbon::MONDAY);
        $twoDaysAgo = $today->copy()->subDays(2);

        $instances = WorkTaskInstance::where('status', WorkTaskInstance::STATUS_COMPLETED)
            ->whereHas('task', fn ($q) => $q->where('user_id', $userId))
            ->with(['task.project', 'task.labels', 'task.program'])
            ->orderByDesc('completed_at')
            ->get();

        $todayList = $instances->filter(fn ($i) => $i->instance_date?->format('Y-m-d') === $todayStr)->values();
        $yesterdayList = $instances->filter(fn ($i) => $i->instance_date?->format('Y-m-d') === $yesterdayStr)->values();
        $thisWeekList = $instances->filter(function ($i) use ($twoDaysAgo, $yesterdayStr, $todayStr, $startOfWeek) {
            $d = $i->instance_date?->format('Y-m-d');
            if (! $d || $d === $todayStr || $d === $yesterdayStr) {
                return false;
            }
            $dt = Carbon::parse($d)->startOfDay();
            return $dt->gte($startOfWeek) && $dt->lte($twoDaysAgo);
        })->values();
        $olderList = $instances->filter(function ($i) use ($startOfWeek) {
            $d = $i->instance_date?->format('Y-m-d');
            return $d && Carbon::parse($d)->startOfDay()->lt($startOfWeek);
        })->values();

        return [
            'today' => $todayList,
            'yesterday' => $yesterdayList,
            'this_week' => $thisWeekList,
            'older' => $olderList,
        ];
    }

    protected function getKanbanData(?int $userId): array
    {
        $columns = $userId ? KanbanColumn::ensureDefaultsForUser($userId) : collect();
        $slugs = $columns->pluck('slug')->all();
        $tasks = $userId
            ? collect($slugs)->mapWithKeys(fn ($status) => [
                $status => CongViecTask::where('user_id', $userId)->where('kanban_status', $status)
                    ->with(['project', 'labels', 'program'])
                    ->orderByRaw('due_date IS NULL, due_date ASC')
                    ->orderByDesc('updated_at')
                    ->get(),
            ])
            : collect($slugs)->mapWithKeys(fn ($s) => [$s => collect()]);

        return [$columns, $tasks];
    }

    protected function getBehaviorRadar(?int $userId): ?array
    {
        if (! $userId || ! config('behavior_intelligence.enabled', true)) {
            return null;
        }
        $policy = DB::table('behavior_user_policy')->where('user_id', $userId)->first();
        $trust = app(AdaptiveTrustGradientService::class)->get($userId, null);
        $cliRow = DB::table('behavior_cognitive_snapshots')->where('user_id', $userId)->orderByDesc('snapshot_date')->first();
        $projection = app(LongTermProjectionService::class)->getOrCompute($userId);

        return [
            'trust_global' => $trust ? round(($trust['trust_execution'] + $trust['trust_honesty'] + $trust['trust_consistency']) / 3, 2) : null,
            'mode' => $policy ? ($policy->mode ?? 'normal') : 'normal',
            'cli' => $cliRow ? (float) $cliRow->cli : null,
            'projection_60d' => $projection['probability_maintain_60d'] ?? null,
        ];
    }

    protected function getEditTask(Request $request, ?int $userId): ?CongViecTask
    {
        if (! $userId || ! $request->filled('edit')) {
            return null;
        }
        $editId = (int) $request->input('edit');
        if ($editId <= 0) {
            return null;
        }

        return CongViecTask::where('id', $editId)->where('user_id', $userId)->with(['project', 'labels', 'program'])->first();
    }

    /**
     * Build program_progress payload for JSON response (toggle/confirm complete).
     */
    public static function buildProgramProgressPayload(int $userId, CongViecTask $task): ?array
    {
        if (! $task->program_id) {
            return null;
        }
        $todayHcm = Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d');
        $progress = app(BehaviorProgramProgressService::class)->getProgressForUi($userId, $task->program_id);

        return [
            'integrity_score' => $progress['integrity_score'],
            'days_elapsed' => $progress['days_elapsed'],
            'days_total' => $progress['days_total'],
            'days_with_completion' => $progress['days_with_completion'],
            'today_done' => WorkTaskInstance::where('instance_date', $todayHcm)->whereHas('task', fn ($q) => $q->where('user_id', $userId)->where('program_id', $task->program_id))->where('status', WorkTaskInstance::STATUS_COMPLETED)->count(),
            'today_total' => WorkTaskInstance::where('instance_date', $todayHcm)->whereHas('task', fn ($q) => $q->where('user_id', $userId)->where('program_id', $task->program_id))->count(),
        ];
    }
}
