{{-- Execution Panel: Header, Progress, Momentum, Focus, Priority tiers, Intelligence cards --}}
@php
    $focusPlan = $focusPlan ?? ['focus' => collect(), 'secondary' => collect(), 'backlog' => collect(), 'total_planned_minutes' => 0, 'available_minutes' => 120];
    $useFocusPlan = $focusPlan['focus']->isNotEmpty();
    $tiers = $tasksTodayTiers ?? ['high' => collect(), 'medium' => collect(), 'low' => collect()];
    $tierConfig = [
        'high' => ['label' => 'Quan trọng nhất', 'icon' => '🔥', 'class' => 'text-amber-600 dark:text-amber-400'],
        'medium' => ['label' => 'Nên làm tiếp theo', 'icon' => '⚡', 'class' => 'text-blue-600 dark:text-blue-400'],
        'low' => ['label' => 'Việc nhỏ', 'icon' => '📌', 'class' => 'text-gray-500 dark:text-gray-400'],
    ];
    $todayPriorityScores = $todayPriorityScores ?? [];
    $executionMetrics = $executionMetrics ?? null;
    $behaviorProfile = $behaviorProfile ?? null;
    $plannedToday = $executionMetrics['planned_today'] ?? 0;
    $completedToday = $executionMetrics['completed_today'] ?? 0;
    $totalToday = $plannedToday + $completedToday;
    $executionRatePct = $totalToday > 0 ? (int) round(($completedToday / $totalToday) * 100) : 0;
    $healthPct = $executionMetrics ? (int) round(($executionMetrics['execution_rate_30d'] ?? 0) * 100) : null;
    $riskTier = $executionMetrics['risk_tier'] ?? 'normal';
    $healthIcon = $riskTier === 'normal' ? '🟢' : ($riskTier === 'warning' ? '🟠' : '🔴');
    $riskTrend = $failureDetection['risk_trend'] ?? 'stable';
    $riskDelta = $failureDetection['risk_delta'] ?? null;
    $riskTrendIcon = $riskTrend === 'rising' ? '↑' : ($riskTrend === 'improving' ? '↓' : '');
    $riskDeltaText = $riskDelta !== null ? ($riskDelta > 0 ? ' ↑ +' . $riskDelta : ' ↓ ' . $riskDelta) : '';
    $profileLabel = $behaviorProfile['profile_label'] ?? '—';
    $focusWindow = '—';
    if (!empty($behaviorProfile['completion_by_hour']) && is_array($behaviorProfile['completion_by_hour'])) {
        $byHour = $behaviorProfile['completion_by_hour'];
        $max = 0;
        $peakStart = null;
        $peakEnd = null;
        foreach ($byHour as $h => $cnt) {
            if ($cnt > $max) { $max = $cnt; $peakStart = $h; $peakEnd = $h; }
        }
        if ($max > 0 && $peakStart !== null) {
            for ($h = $peakStart - 1; $h >= 0 && ($byHour[$h] ?? 0) >= $max * 0.5; $h--) { $peakStart = $h; }
            for ($h = $peakEnd + 1; $h < 24 && ($byHour[$h] ?? 0) >= $max * 0.5; $h++) { $peakEnd = $h; }
            $focusWindow = sprintf('%02d:00–%02d:00', $peakStart, min(23, $peakEnd + 1));
        }
    }
    $momentumStreak = $completedToday;
    $weekday = now()->locale('vi')->dayName;
    $avail = $focusPlan['available_minutes'] ?: 1;
    $plannedMin = $focusPlan['total_planned_minutes'] ?? 0;
    $workloadRatio = $plannedMin / $avail;
    $workloadLabel = $workloadRatio > 1.0 ? 'Quá tải' : ($workloadRatio >= 0.4 ? 'Cân bằng' : 'Nhẹ');
    $workloadClass = $workloadRatio > 1.0 ? 'text-red-600 dark:text-red-400' : ($workloadRatio >= 0.4 ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400');
    $estimatedFinish = null;
    if ($focusPlan['total_planned_minutes'] > 0) {
        $finish = now()->copy()->addMinutes($focusPlan['total_planned_minutes']);
        $estimatedFinish = $finish->format('H:i');
    }
    $momentumStreakToday = (int) ($executionMetrics['momentum_streak_today'] ?? 0);
    $allDoneToday = $plannedToday == 0 && $completedToday > 0;
    $momentumState = 'break';
    $momentumCopy = '';
    if ($allDoneToday) {
        $momentumState = 'strong';
        $momentumCopy = 'Hôm nay đã hoàn thành hết. 🎉';
    } elseif ($completedToday === 0) {
        $momentumState = 'break';
        $momentumCopy = 'Bạn chưa hoàn thành task nào hôm nay. Bắt đầu với một việc nhỏ.';
    } elseif ($completedToday >= 1 && $completedToday <= 2) {
        $momentumState = 'building';
        $momentumCopy = 'Bạn đang xây momentum. Tiếp tục 1–2 việc nữa để đạt mạnh.';
        if ($momentumStreakToday >= 2) {
            $momentumCopy .= ' 🔥 ' . $momentumStreakToday . ' việc liên tiếp.';
        }
    } elseif ($completedToday >= 3 && $completedToday <= 5) {
        $momentumState = 'strong';
        $momentumCopy = 'Momentum mạnh. Bạn đã hoàn thành ' . $completedToday . ' việc. Giữ nhịp nhé.';
        if ($momentumStreakToday >= 2) {
            $momentumCopy .= ' 🔥 ' . $momentumStreakToday . ' việc liên tiếp.';
        }
    } else {
        $momentumState = 'peak';
        $momentumCopy = 'Đỉnh momentum. ' . $completedToday . ' việc hoàn thành. Rất tốt.';
        if ($momentumStreakToday >= 2) {
            $momentumCopy .= ' 🔥 ' . $momentumStreakToday . ' việc liên tiếp.';
        }
    }
@endphp
<div class="w-full max-w-[880px] space-y-5">
    {{-- Execution Header --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-800/50 px-4 py-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">Hôm nay — {{ $weekday }}</h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $totalToday }} việc · {{ $focusPlan['available_minutes'] }} phút tập trung</p>
            </div>
            <div class="flex flex-wrap items-center gap-4 text-sm">
                <span class="flex items-center gap-1.5" title="Trạng thái thực thi">
                    {{ $healthIcon }} <span class="font-medium text-gray-700 dark:text-gray-300">Trạng thái thực thi:</span>
                    <span class="font-semibold">{{ $healthPct !== null ? $healthPct . '%' : '—' }}{{ $riskDeltaText !== '' ? $riskDeltaText : ($riskTrendIcon ? ' ' . $riskTrendIcon : '') }}</span>
                </span>
                <span class="text-gray-400 dark:text-gray-500">|</span>
                <span><span class="text-gray-500 dark:text-gray-400">Khối lượng:</span> <span class="font-medium {{ $workloadClass }}">{{ $workloadLabel }}</span></span>
                <span class="text-gray-400 dark:text-gray-500">|</span>
                <span><span class="text-gray-500 dark:text-gray-400">Hồ sơ:</span> <span class="font-medium">{{ $profileLabel }}</span></span>
                <span class="text-gray-400 dark:text-gray-500">|</span>
                <span><span class="text-gray-500 dark:text-gray-400">Cửa sổ tập trung:</span> <span class="font-medium">{{ $focusWindow }}</span></span>
            </div>
        </div>
    </div>

    {{-- Today Progress + Momentum --}}
    @if($totalToday > 0)
        <div class="flex flex-wrap items-center gap-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 px-4 py-3">
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tiến độ hôm nay</span>
                <span class="font-semibold text-gray-900 dark:text-white">Đã xong: {{ $completedToday }} / {{ $totalToday }}</span>
                <span class="text-sm text-gray-500 dark:text-gray-400">· Tỷ lệ hoàn thành: {{ $executionRatePct }}%</span>
            </div>
            <div class="h-2 flex-1 max-w-[160px] rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                <div class="h-full rounded-full bg-brand-500 dark:bg-brand-600 transition-all" style="width: {{ min(100, $executionRatePct) }}%"></div>
            </div>
        </div>
        @if($totalToday > 0)
            @php
                $momentumBoxClass = $momentumState === 'break' ? 'border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50' : ($momentumState === 'building' ? 'border-amber-200 dark:border-amber-800 bg-amber-50/80 dark:bg-amber-900/20' : ($momentumState === 'strong' ? 'border-green-200 dark:border-green-800 bg-green-50/80 dark:bg-green-900/20' : 'border-brand-200 dark:border-brand-800 bg-brand-50/80 dark:bg-brand-900/20'));
                $momentumTextClass = $momentumState === 'break' ? 'text-gray-700 dark:text-gray-300' : ($momentumState === 'building' ? 'text-amber-800 dark:text-amber-200' : ($momentumState === 'strong' ? 'text-green-800 dark:text-green-200' : 'text-brand-800 dark:text-brand-200'));
                $momentumIcon = $momentumState === 'break' ? '⚠' : ($momentumState === 'building' ? '🔥' : ($momentumState === 'strong' ? '🔥' : '⚡'));
                $momentumTitle = $momentumState === 'break' ? 'Nghỉ momentum' : ($momentumState === 'building' ? 'Đang xây momentum' : ($allDoneToday ? 'Hoàn thành hết' : ($momentumState === 'strong' ? 'Momentum mạnh' : 'Đỉnh momentum')));
            @endphp
            <div class="rounded-xl border px-4 py-2.5 text-sm {{ $momentumBoxClass }}">
                <span class="font-medium {{ $momentumTextClass }}">{{ $momentumIcon }} {{ $momentumTitle }}</span>
                <span class="text-gray-700 dark:text-gray-300"> {{ $momentumCopy }}</span>
            </div>
        @endif
    @endif

    @if($tasksToday->isNotEmpty())
        <div class="space-y-6" id="today-task-list">
            {{-- 🎯 FOCUS HÔM NAY — khi đã hoàn thành hết thì chỉ hiện thông báo --}}
            @if($useFocusPlan && $focusPlan['focus']->isNotEmpty())
                <div class="space-y-2">
                    @if($allDoneToday)
                        <div class="rounded-xl border border-green-200 dark:border-green-800 bg-green-50/80 dark:bg-green-900/20 px-4 py-4 text-center">
                            <p class="text-lg font-semibold text-green-800 dark:text-green-200">🎉 Hôm nay đã hoàn thành</p>
                            <p class="mt-1 text-sm text-green-700 dark:text-green-300">Đã xong {{ $completedToday }} / {{ $totalToday }} việc.</p>
                        </div>
                    @else
                        <p class="text-sm font-bold uppercase tracking-wide text-amber-600 dark:text-amber-400">🎯 Focus hôm nay ({{ $focusPlan['focus']->count() }} việc)</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">⚡ Focus order · {{ $focusPlan['total_planned_minutes'] }} phút / {{ $focusPlan['available_minutes'] }} phút @if($estimatedFinish)<span class="font-medium text-amber-600 dark:text-amber-400">· Dự kiến xong lúc: {{ $estimatedFinish }}</span>@endif</p>
                        @php
                            $focusStart = now()->copy();
                            $focusTimeline = [];
                            foreach ($focusPlan['focus'] as $inst) {
                                $mins = app(\App\Services\TaskDurationLearningService::class)->getPredictedMinutes($inst->task->id) ?? $inst->task->estimated_duration ?? 30;
                                $dueTime = $inst->task->due_time ? substr($inst->task->due_time, 0, 5) : null;
                                $focusTimeline[] = ['time' => $focusStart->format('H:i'), 'title' => $inst->task->title, 'minutes' => $mins, 'due_time' => $dueTime];
                                $focusStart->addMinutes($mins);
                            }
                            $focusSlackMinutes = max(0, $focusPlan['available_minutes'] - $focusPlan['total_planned_minutes']);
                        @endphp
                        @if(count($focusTimeline) > 0)
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/30 px-3 py-2 text-xs">
                                <p class="font-semibold text-gray-500 dark:text-gray-400 mb-1">⚡ Focus order · Dòng thời gian (giờ bắt đầu đề xuất)</p>
                                @foreach($focusTimeline as $t)
                                    <p class="text-gray-700 dark:text-gray-300"><span class="font-mono text-gray-500 dark:text-gray-400">{{ $t['time'] }}</span> {{ $t['title'] }}@if(!empty($t['due_time'])) <span class="text-gray-500 dark:text-gray-400">— Hạn: {{ $t['due_time'] }}</span>@endif</p>
                                @endforeach
                            </div>
                        @endif
                        @if($focusSlackMinutes > 0)
                            <p class="text-xs text-amber-600 dark:text-amber-400">⚡ Bạn còn {{ $focusSlackMinutes }} phút trống. Thêm task?</p>
                        @endif
                        <ol class="space-y-1 list-none pl-0">
                            @foreach($focusPlan['focus'] as $idx => $instance)
                                <li>
                                    @include('pages.cong-viec.partials.task-row', [
                                        'instance' => $instance,
                                        'task' => $instance->task,
                                        'toggleCompleteUrl' => route('cong-viec.instances.toggle-complete', $instance->id),
                                        'confirmCompleteUrl' => route('cong-viec.instances.confirm-complete', $instance->id),
                                        'completed' => $instance->status === \App\Models\WorkTaskInstance::STATUS_COMPLETED,
                                        'asTodayRow' => true,
                                        'streak' => ($taskStreaks ?? [])[$instance->work_task_id] ?? null,
                                        'priorityScore' => $todayPriorityScores[$instance->id]['score'] ?? null,
                                        'showIntelligence' => true,
                                        'focusOrder' => $idx + 1,
                                    ])
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
                @if($focusPlan['secondary']->isNotEmpty())
                    <div class="space-y-1">
                        <p class="text-xs font-semibold uppercase tracking-wide text-blue-600 dark:text-blue-400">⚡ Nên làm tiếp (nếu còn thời gian)</p>
                        <ul class="space-y-1">
                                @foreach($focusPlan['secondary'] as $instance)
                                @include('pages.cong-viec.partials.task-row', ['instance' => $instance, 'task' => $instance->task, 'toggleCompleteUrl' => route('cong-viec.instances.toggle-complete', $instance->id), 'confirmCompleteUrl' => route('cong-viec.instances.confirm-complete', $instance->id), 'completed' => $instance->status === \App\Models\WorkTaskInstance::STATUS_COMPLETED, 'asTodayRow' => true, 'streak' => ($taskStreaks ?? [])[$instance->work_task_id] ?? null, 'priorityScore' => $todayPriorityScores[$instance->id]['score'] ?? null, 'showIntelligence' => true])
                            @endforeach
                        </ul>
                    </div>
                @endif
            @else
                {{-- Theo độ ưu tiên (khi không dùng focus plan) --}}
                @foreach($tierConfig as $tierKey => $config)
                    @if($tiers[$tierKey]->isNotEmpty())
                        <div class="space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-wide {{ $config['class'] }}">{{ $config['icon'] }} {{ $config['label'] }}</p>
                            <ul class="space-y-1">
                                @foreach($tiers[$tierKey] as $instance)
                                    @include('pages.cong-viec.partials.task-row', ['instance' => $instance, 'task' => $instance->task, 'toggleCompleteUrl' => route('cong-viec.instances.toggle-complete', $instance->id), 'confirmCompleteUrl' => route('cong-viec.instances.confirm-complete', $instance->id), 'completed' => $instance->status === \App\Models\WorkTaskInstance::STATUS_COMPLETED, 'asTodayRow' => true, 'streak' => ($taskStreaks ?? [])[$instance->work_task_id] ?? null, 'priorityScore' => $todayPriorityScores[$instance->id]['score'] ?? null, 'showIntelligence' => true])
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach
            @endif
        </div>
    @else
        <p class="text-gray-500 dark:text-gray-400">{{ $coachingNarrative['empty_today_copy'] ?? 'Hôm nay bạn chưa có cam kết nào. Thêm một việc để bắt đầu.' }}</p>
    @endif

    <button type="button" x-show="!showAddTask" x-cloak @click="addTaskKanbanStatus = 'backlog'; showAddTask = true" class="flex items-center gap-2 rounded-lg py-2 text-left text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-red-500">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
        </span>
        <span class="text-sm">Thêm công việc</span>
    </button>
</div>
