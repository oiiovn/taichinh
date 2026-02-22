<?php

namespace App\Services;

use App\Models\BehaviorProgram;
use App\Models\CongViecTask;
use App\Models\KanbanColumn;
use App\Models\Label;
use App\Models\Project;
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

        $tasksToday = $this->getTasksToday($userId, $todayHcm);
        $tasksUpcoming = $this->getTasksUpcoming($userId, $todayHcm);
        $tasksInbox = $this->getTasksInbox($userId);
        $tasksCompleted = $this->getTasksCompleted($userId);
        [$kanbanColumns, $kanbanTasks] = $this->getKanbanData($userId);

        $userLabels = $request->user() ? Label::where('user_id', $request->user()->id)->orderBy('name')->get() : collect();
        $userProjects = $request->user() ? Project::where('user_id', $request->user()->id)->orderBy('name')->get() : collect();
        $userPrograms = $request->user() ? BehaviorProgram::where('user_id', $request->user()->id)->where('status', BehaviorProgram::STATUS_ACTIVE)->orderBy('title')->get() : collect();

        $activeProgram = $userId && $userPrograms->isNotEmpty() ? $userPrograms->first() : null;
        $activeProgramProgress = null;
        $todayProgramTaskTotal = 0;
        $todayProgramTaskDone = 0;
        if ($activeProgram && $userId) {
            $activeProgramProgress = app(BehaviorProgramProgressService::class)->getProgressForUi($userId, $activeProgram->id);
            $todayProgramTasks = CongViecTask::where('user_id', $userId)->where('program_id', $activeProgram->id)
                ->where(function ($q) use ($todayHcm) {
                    $q->where('due_date', $todayHcm)->orWhereNull('due_date');
                })
                ->get();
            $todayProgramTaskTotal = $todayProgramTasks->count();
            $todayProgramTaskDone = $todayProgramTasks->where('completed', true)->count();
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

        $coachingNarrative = app(CoachingNarrativeService::class)->getTodayNarrative(
            $activeProgram,
            $activeProgramProgress,
            $behaviorRadar ?? [],
            $behaviorProjection,
            $interfaceAdaptation,
            $todayProgramTaskTotal,
            $todayProgramTaskDone,
            $tasksToday->count(),
            $userId
        );

        $this->logCoachingInterventions(
            $userId,
            $behaviorPolicy,
            $interfaceAdaptation,
            $coachingNarrative,
            $behaviorProjection
        );

        return [
            'tasksToday' => $tasksToday,
            'tasksUpcoming' => $tasksUpcoming,
            'tasksInbox' => $tasksInbox,
            'tasksCompleted' => $tasksCompleted,
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

    protected function getTasksToday(?int $userId, string $todayHcm): Collection
    {
        if (! $userId) {
            return collect();
        }
        // Task theo ngày (occurringOnDate cần due_date not null)
        $tasks = CongViecTask::occurringOnDate($todayHcm)->filter(fn ($t) => ! $t->completed);
        // Task thuộc chương trình: mọi task chưa xong có program_id — để list luôn thấy giống Kanban
        $programTasks = CongViecTask::where('user_id', $userId)
            ->where('completed', false)
            ->whereNotNull('program_id')
            ->with(['project', 'labels', 'program'])
            ->get();
        $tasks = $tasks->concat($programTasks)->unique('id')->values();
        if ($tasks->isNotEmpty()) {
            $tasks->load(['project', 'labels', 'program']);
        }

        return $tasks;
    }

    /**
     * Task chưa đến hạn: due_date > hôm nay, chưa hoàn thành.
     */
    protected function getTasksUpcoming(?int $userId, string $todayHcm): Collection
    {
        if (! $userId) {
            return collect();
        }

        return CongViecTask::where('user_id', $userId)
            ->where('completed', false)
            ->whereNotNull('due_date')
            ->where('due_date', '>', $todayHcm)
            ->with(['project', 'labels', 'program'])
            ->orderBy('due_date')
            ->orderByRaw('due_time IS NULL, due_time ASC')
            ->get();
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

    protected function getTasksCompleted(?int $userId): Collection
    {
        if (! $userId) {
            return collect();
        }

        return CongViecTask::where('user_id', $userId)->where('completed', true)
            ->with(['project', 'labels', 'program'])
            ->orderByDesc('updated_at')
            ->get();
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
            'today_done' => CongViecTask::where('user_id', $userId)->where('program_id', $task->program_id)->where('completed', true)->where('due_date', $todayHcm)->count(),
            'today_total' => CongViecTask::where('user_id', $userId)->where('program_id', $task->program_id)->where('due_date', $todayHcm)->count(),
        ];
    }
}
