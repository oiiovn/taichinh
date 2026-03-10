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
    private const VALID_TABS = ['tong-quan', 'hom-nay', 'du-kien', 'hoan-thanh'];

    public function getIndexData(Request $request): array
    {
        $tab = $request->get('tab', 'tong-quan');
        if (! in_array($tab, self::VALID_TABS, true)) {
            $tab = 'tong-quan';
        }

        return match ($tab) {
            'hom-nay' => $this->getTodayData($request),
            'du-kien' => $this->getUpcomingData($request),
            'hoan-thanh' => $this->getCompletedData($request),
            'tong-quan' => $this->getOverviewData($request),
            default => $this->getTodayData($request),
        };
    }

    /**
     * Tab Hôm nay: chỉ ensure, tasks today, streaks, execution engine, focus session, missed window, labels, projects, programs, editTask.
     */
    protected function getTodayData(Request $request): array
    {
        $todayHcm = Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d');
        $userId = $request->user()?->id;

        if ($userId) {
            app(EnsureTaskInstancesService::class)->ensureForUserAndDate($userId, $todayHcm);
        }
        $tasksToday = $this->getTasksToday($userId, $todayHcm);
        $taskStreaks = $this->getTaskStreaksForInstances($userId, $tasksToday);

        $userLabels = $userId ? Label::where('user_id', $userId)->orderBy('name')->get() : collect();
        $userProjects = $userId ? Project::where('user_id', $userId)->orderBy('name')->get() : collect();
        $userPrograms = $userId ? BehaviorProgram::where('user_id', $userId)->where('status', BehaviorProgram::STATUS_ACTIVE)->orderBy('title')->get() : collect();
        $activeProgram = $userId && $userPrograms->isNotEmpty() ? $userPrograms->first() : null;

        $execution = $userId
            ? app(ExecutionEngineService::class)->run($userId, $todayHcm, $tasksToday, $taskStreaks, $activeProgram?->id)
            : null;

        $behaviorProfile = $execution['behavior_profile'] ?? null;
        $routineDetection = $execution['routine_detection'] ?? null;
        $failureDetection = $execution['failure_detection'] ?? null;
        $todayPriority = $execution['today_priority'] ?? ['sorted' => $tasksToday, 'tiers' => ['high' => collect(), 'medium' => collect(), 'low' => collect()], 'scores' => []];
        $focusPlan = $execution['focus_plan'] ?? ['focus' => collect(), 'secondary' => collect(), 'backlog' => collect(), 'later' => [], 'missed_window' => collect(), 'total_planned_minutes' => 0, 'available_minutes' => 120];
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

        $focusSessionPayload = null;
        if ($userId) {
            $fs = app(FocusSessionService::class)->get($userId);
            if ($fs && ! empty($fs['instance_id'])) {
                $fi = WorkTaskInstance::with('task')->find($fs['instance_id']);
                if ($fi && $fi->status !== WorkTaskInstance::STATUS_COMPLETED) {
                    $focusSessionPayload = [
                        'instance_id' => (int) $fs['instance_id'],
                        'started_at' => (int) $fs['started_at'],
                        'title' => $fi->task?->title ?? '',
                    ];
                }
            }
        }

        $missedWindowPrompt = null;
        $missedWindow = $focusPlan['missed_window'] ?? collect();
        if ($missedWindow->isNotEmpty()) {
            $first = $missedWindow->first();
            if ($first && $first->status !== WorkTaskInstance::STATUS_COMPLETED) {
                $missedWindowPrompt = [
                    'instance_id' => $first->id,
                    'title' => $first->task?->title ?? '',
                    'toggle_url' => route('cong-viec.instances.toggle-complete', $first->id),
                ];
            }
        }

        $coachingNarrative = ['empty_today_copy' => 'Hôm nay bạn chưa có cam kết nào. Thêm một việc để bắt đầu.'];

        return array_merge($this->sharedMinimalData($request, $userId, $todayHcm), [
            'focusSession' => $focusSessionPayload,
            'missedWindowPrompt' => $missedWindowPrompt,
            'routineDetection' => $routineDetection,
            'tasksToday' => $todayPriority['sorted'],
            'tasksTodayTiers' => $todayPriority['tiers'],
            'todayPriorityScores' => $todayPriority['scores'],
            'focusPlan' => $focusPlan,
            'taskStreaks' => $taskStreaks,
            'todayHcm' => $todayHcm,
            'userLabels' => $userLabels,
            'userProjects' => $userProjects,
            'userPrograms' => $userPrograms,
            'activeProgram' => $activeProgram,
            'activeProgramProgress' => $activeProgramProgress,
            'todayProgramTaskTotal' => $todayProgramTaskTotal,
            'todayProgramTaskDone' => $todayProgramTaskDone,
            'behaviorProfile' => $behaviorProfile,
            'failureDetection' => $failureDetection,
            'executionMetrics' => $executionMetrics,
            'coachingNarrative' => $coachingNarrative,
            'taskCreationContext' => $this->defaultTaskCreationContext(),
            'behaviorPolicy' => null,
            'behaviorProjection' => null,
            'kanbanColumns' => collect(),
            'kanbanTasks' => collect(),
        ]);
    }

    /**
     * Tab Dự kiến: upcoming instances, labels, projects, programs, editTask.
     */
    protected function getUpcomingData(Request $request): array
    {
        $todayHcm = Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d');
        $userId = $request->user()?->id;

        $tasksUpcoming = $this->getInstancesUpcoming($userId, $todayHcm);
        $userLabels = $userId ? Label::where('user_id', $userId)->orderBy('name')->get() : collect();
        $userProjects = $userId ? Project::where('user_id', $userId)->orderBy('name')->get() : collect();
        $userPrograms = $userId ? BehaviorProgram::where('user_id', $userId)->where('status', BehaviorProgram::STATUS_ACTIVE)->orderBy('title')->get() : collect();

        return array_merge($this->sharedMinimalData($request, $userId, $todayHcm), [
            'tasksUpcoming' => $tasksUpcoming,
            'userLabels' => $userLabels,
            'userProjects' => $userProjects,
            'userPrograms' => $userPrograms,
            'todayHcm' => $todayHcm,
            'focusSession' => null,
            'missedWindowPrompt' => null,
            'routineDetection' => null,
            'tasksToday' => collect(),
            'tasksTodayTiers' => ['high' => collect(), 'medium' => collect(), 'low' => collect()],
            'todayPriorityScores' => [],
            'focusPlan' => ['focus' => collect(), 'secondary' => collect(), 'backlog' => collect(), 'later' => [], 'missed_window' => collect(), 'total_planned_minutes' => 0, 'available_minutes' => 120],
            'taskStreaks' => [],
            'completedInstancesGrouped' => ['today' => collect(), 'yesterday' => collect(), 'this_week' => collect(), 'older' => collect()],
            'kanbanColumns' => collect(),
            'kanbanTasks' => collect(),
            'activeProgram' => null,
            'activeProgramProgress' => null,
            'todayProgramTaskTotal' => 0,
            'todayProgramTaskDone' => 0,
            'behaviorRadar' => null,
            'behaviorPolicy' => null,
            'behaviorProjection' => null,
            'interfaceAdaptation' => [],
            'coachingNarrative' => [],
            'behaviorProfile' => null,
            'failureDetection' => null,
            'insightPayload' => null,
            'executionMetrics' => null,
            'taskCreationContext' => $this->defaultTaskCreationContext(),
            'tasksInbox' => collect(),
        ]);
    }

    /**
     * Tab Hoàn thành: completed instances (4 query), labels, projects, programs, editTask.
     */
    protected function getCompletedData(Request $request): array
    {
        $todayHcm = Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d');
        $userId = $request->user()?->id;

        $completedInstancesGrouped = $this->getCompletedInstancesGrouped($userId);
        $userLabels = $userId ? Label::where('user_id', $userId)->orderBy('name')->get() : collect();
        $userProjects = $userId ? Project::where('user_id', $userId)->orderBy('name')->get() : collect();
        $userPrograms = $userId ? BehaviorProgram::where('user_id', $userId)->where('status', BehaviorProgram::STATUS_ACTIVE)->orderBy('title')->get() : collect();

        return array_merge($this->sharedMinimalData($request, $userId, $todayHcm), [
            'completedInstancesGrouped' => $completedInstancesGrouped,
            'userLabels' => $userLabels,
            'userProjects' => $userProjects,
            'userPrograms' => $userPrograms,
            'todayHcm' => $todayHcm,
            'focusSession' => null,
            'missedWindowPrompt' => null,
            'routineDetection' => null,
            'tasksToday' => collect(),
            'tasksTodayTiers' => ['high' => collect(), 'medium' => collect(), 'low' => collect()],
            'todayPriorityScores' => [],
            'focusPlan' => ['focus' => collect(), 'secondary' => collect(), 'backlog' => collect(), 'later' => [], 'missed_window' => collect(), 'total_planned_minutes' => 0, 'available_minutes' => 120],
            'taskStreaks' => [],
            'tasksUpcoming' => collect(),
            'tasksInbox' => collect(),
            'kanbanColumns' => collect(),
            'kanbanTasks' => collect(),
            'activeProgram' => null,
            'activeProgramProgress' => null,
            'todayProgramTaskTotal' => 0,
            'todayProgramTaskDone' => 0,
            'behaviorRadar' => null,
            'behaviorPolicy' => null,
            'behaviorProjection' => null,
            'interfaceAdaptation' => [],
            'coachingNarrative' => [],
            'behaviorProfile' => null,
            'failureDetection' => null,
            'insightPayload' => null,
            'executionMetrics' => null,
            'taskCreationContext' => $this->defaultTaskCreationContext(),
        ]);
    }

    /**
     * Tab Tổng quan: full context (radar, projection, adaptation, coaching, policy, kanban, inbox, execution cho today).
     */
    protected function getOverviewData(Request $request): array
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

        $userLabels = $userId ? Label::where('user_id', $userId)->orderBy('name')->get() : collect();
        $userProjects = $userId ? Project::where('user_id', $userId)->orderBy('name')->get() : collect();
        $userPrograms = $userId ? BehaviorProgram::where('user_id', $userId)->where('status', BehaviorProgram::STATUS_ACTIVE)->orderBy('title')->get() : collect();
        $activeProgram = $userId && $userPrograms->isNotEmpty() ? $userPrograms->first() : null;

        $execution = $userId
            ? app(ExecutionEngineService::class)->run($userId, $todayHcm, $tasksToday, $taskStreaks, $activeProgram?->id)
            : null;

        $behaviorProfile = $execution['behavior_profile'] ?? null;
        $routineDetection = $execution['routine_detection'] ?? null;
        $failureDetection = $execution['failure_detection'] ?? null;
        $todayPriority = $execution['today_priority'] ?? ['sorted' => $tasksToday, 'tiers' => ['high' => collect(), 'medium' => collect(), 'low' => collect()], 'scores' => []];
        $focusPlan = $execution['focus_plan'] ?? ['focus' => collect(), 'secondary' => collect(), 'backlog' => collect(), 'later' => [], 'missed_window' => collect(), 'total_planned_minutes' => 0, 'available_minutes' => 120];
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
            : $this->defaultTaskCreationContext();

        $focusSessionPayload = null;
        if ($userId) {
            $fs = app(FocusSessionService::class)->get($userId);
            if ($fs && ! empty($fs['instance_id'])) {
                $fi = WorkTaskInstance::with('task')->find($fs['instance_id']);
                if ($fi && $fi->status !== WorkTaskInstance::STATUS_COMPLETED) {
                    $focusSessionPayload = [
                        'instance_id' => (int) $fs['instance_id'],
                        'started_at' => (int) $fs['started_at'],
                        'title' => $fi->task?->title ?? '',
                    ];
                }
            }
        }

        $missedWindowPrompt = null;

        return array_merge($this->sharedMinimalData($request, $userId, $todayHcm), [
            'focusSession' => $focusSessionPayload,
            'missedWindowPrompt' => $missedWindowPrompt,
            'routineDetection' => $routineDetection,
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
            'behaviorPolicy' => $behaviorPolicy,
            'behaviorProjection' => $behaviorProjection,
            'interfaceAdaptation' => $interfaceAdaptation,
            'coachingNarrative' => $coachingNarrative,
            'behaviorProfile' => $behaviorProfile,
            'failureDetection' => $failureDetection,
            'insightPayload' => $insightPayload,
            'executionMetrics' => $executionMetrics,
            'taskCreationContext' => $taskCreationContext,
        ]);
    }

    protected function sharedMinimalData(Request $request, ?int $userId, string $todayHcm): array
    {
        return [
            'editTask' => $this->getEditTask($request, $userId),
        ];
    }

    protected function defaultTaskCreationContext(): array
    {
        return [
            'focus_window' => '—',
            'workload_pct' => 0,
            'suggested_priority' => null,
            'suggested_priority_value' => null,
            'best_time' => null,
            'execution_stage' => 'planning',
            'risk_tier' => 'normal',
            'overload_hint' => null,
            'capacity_remaining_minutes' => 120,
            'task_fit_score' => 50,
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
     * Hôm nay: instance theo ngày (đã ensure), chỉ lấy pending/skipped. Select cột cần thiết.
     */
    protected function getTasksToday(?int $userId, string $todayHcm): Collection
    {
        if (! $userId) {
            return collect();
        }
        return WorkTaskInstance::where('instance_date', $todayHcm)
            ->whereHas('task', fn ($q) => $q->where('user_id', $userId))
            ->whereIn('status', [WorkTaskInstance::STATUS_PENDING, WorkTaskInstance::STATUS_SKIPPED])
            ->select([
                'id',
                'work_task_id',
                'instance_date',
                'status',
                'completed_at',
                'actual_duration',
                'focus_started_at',
                'focus_last_activity_at',
                'focus_stopped_at',
                'focus_recorded_minutes',
            ])
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
     * Tab Hoàn thành: 4 query WHERE theo từng nhóm thay vì load all rồi filter PHP.
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

        $scope = fn ($q) => $q->where('user_id', $userId);

        $todayList = WorkTaskInstance::where('status', WorkTaskInstance::STATUS_COMPLETED)
            ->whereHas('task', $scope)
            ->with(['task.project', 'task.labels', 'task.program'])
            ->whereDate('instance_date', $todayStr)
            ->orderByDesc('completed_at')
            ->get();

        $yesterdayList = WorkTaskInstance::where('status', WorkTaskInstance::STATUS_COMPLETED)
            ->whereHas('task', $scope)
            ->with(['task.project', 'task.labels', 'task.program'])
            ->whereDate('instance_date', $yesterdayStr)
            ->orderByDesc('completed_at')
            ->get();

        $thisWeekList = WorkTaskInstance::where('status', WorkTaskInstance::STATUS_COMPLETED)
            ->whereHas('task', $scope)
            ->with(['task.project', 'task.labels', 'task.program'])
            ->whereBetween('instance_date', [$startOfWeek->format('Y-m-d'), $twoDaysAgo->format('Y-m-d')])
            ->whereNotIn('instance_date', [$todayStr, $yesterdayStr])
            ->orderByDesc('completed_at')
            ->get();

        $olderList = WorkTaskInstance::where('status', WorkTaskInstance::STATUS_COMPLETED)
            ->whereHas('task', $scope)
            ->with(['task.project', 'task.labels', 'task.program'])
            ->where('instance_date', '<', $startOfWeek->format('Y-m-d'))
            ->orderByDesc('completed_at')
            ->get();

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
