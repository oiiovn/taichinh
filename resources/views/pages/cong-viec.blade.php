@extends('layouts.cong-viec')

@section('congViecContent')
<style>
.task-checkbox { -webkit-appearance: none; appearance: none; }
.task-checkbox:checked { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='20 6 9 17 4 12'/%3E%3C/svg%3E"); background-size: 12px; background-position: center; background-repeat: no-repeat; }
</style>
@php
    $validTabs = ['tong-quan', 'hom-nay', 'du-kien', 'hoan-thanh'];
    $tab = in_array(request('tab'), $validTabs) ? request('tab') : 'tong-quan';
    $toggleCompleteUrl = route('cong-viec.tasks.toggle-complete', ['id' => '__ID__']);
    $confirmCompleteUrl = route('cong-viec.tasks.confirm-complete', ['id' => '__ID__']);
    $destroyUrlTemplate = route('cong-viec.tasks.destroy', '__ID__');
    $behaviorEventsUrl = route('cong-viec.behavior-events.store');
@endphp
@include('pages.cong-viec.partials.scripts')
@if(!empty($errorMessage))
    <div class="mb-4 rounded-xl border-2 border-red-300 border-l-4 border-l-red-500 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/25 dark:text-red-200" role="alert">
        {{ $errorMessage }}
    </div>
@endif
<div x-data="congViecPage()" x-init="init()" @cong-viec-layout.window="layout = $event.detail.layout; try { localStorage.setItem('congViecLayout', layout); } catch(e) {}" @open-add-task.window="showAddTask = true; addTaskKanbanStatus = $event.detail?.kanban_status || 'backlog'" @cong-viec-require-confirm.window="openConfirmCompleteModal($event.detail.taskId, $event.detail.payload, $event.detail.p, $event.detail.instanceId, $event.detail.confirmInstanceUrl, $event.detail.taskTitle)" @form-dropdown-close-others.window="const id = $event.detail; if (id !== 'more' && id !== 'labels' && id !== 'location' && id !== 'inbox') { showMoreOptions = false; showLabelsPanel = false; showLocationPanel = false; showInboxDropdown = false; }">
@if($tab === 'tong-quan' && isset($behaviorPolicy) && $behaviorPolicy && in_array($behaviorPolicy->mode, ['micro_goal', 'reduced_reminder']))
    <div id="policy-feedback-banner" class="mb-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 border-l-4 border-l-brand-500 pl-4 py-3 text-sm text-gray-900 dark:text-white" data-policy-mode="{{ $behaviorPolicy->mode }}">
        @if($behaviorPolicy->mode === 'micro_goal')
            <p class="mb-2">Đề xuất: Chế độ mục tiêu nhỏ — tập trung ít việc hơn để ổn định nhịp.</p>
        @else
            <p class="mb-2">Một số thói quen đã ổn định; hệ thống giảm nhắc nhở cho bạn.</p>
        @endif
        <p class="mb-2 text-gray-600 dark:text-gray-300">Bạn muốn thế nào?</p>
        <div class="flex flex-wrap gap-2">
            <button type="button" onclick="window.__congViecPolicyFeedbackClick('accepted')" class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-700 dark:bg-brand-500 dark:hover:bg-brand-600 cursor-pointer">Áp dụng</button>
            <button type="button" onclick="window.__congViecPolicyFeedbackClick('ignored')" class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">Bỏ qua</button>
            <button type="button" onclick="window.__congViecPolicyFeedbackClick('rejected')" class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">Nhắc lại sau</button>
        </div>
    </div>
@endif
@if(isset($failureDetection) && $failureDetection['risk_tier'] !== 'normal')
    @php
        $riskTier = $failureDetection['risk_tier'] ?? 'warning';
        $riskIcon = $riskTier === 'collapse' ? '🔴' : '🟠';
    @endphp
    <div class="mb-4 rounded-xl border-2 {{ $riskTier === 'collapse' ? 'border-red-300 border-l-4 border-l-red-500 bg-red-50 dark:border-red-800 dark:bg-red-900/25' : 'border-amber-300 border-l-4 border-l-amber-500 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/25' }} px-4 py-4 text-sm">
        <p class="text-base font-bold text-gray-900 dark:text-white">{{ $riskIcon }} {{ $riskTier === 'collapse' ? 'Nguy cơ sụt' : 'Cảnh báo' }} — Phát hiện rủi ro thực thi</p>
        <p class="mt-1 text-gray-700 dark:text-gray-300">
            @if(!empty($failureDetection['skip_streak_days']) && $failureDetection['skip_streak_days'] >= 1)
                Bạn đã {{ $failureDetection['skip_streak_days'] }} ngày liên tiếp không hoàn thành cam kết.
            @endif
            @if(!empty($failureDetection['delay_count_30d']))
                Trong 30 ngày có {{ $failureDetection['delay_count_30d'] }} lần hoàn thành trễ hạn.
            @endif
            @if(empty($failureDetection['skip_streak_days']) && empty($failureDetection['delay_count_30d']))
                {{ $failureDetection['collapse_risk_message'] ?? 'Nguy cơ trượt cam kết.' }}
            @endif
        </p>
        @if(!empty($failureDetection['suggestions']))
            <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Đề xuất:</p>
            <ul class="mt-1 space-y-0.5 text-gray-700 dark:text-gray-300">
                @foreach($failureDetection['suggestions'] as $sug)
                    <li class="flex items-start gap-2"><span class="text-amber-600 dark:text-amber-400">•</span><span>{{ $sug }}</span></li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
@php
    $insightMessage = (isset($behaviorProjection['suggestion']) && (string) ($behaviorProjection['suggestion'] ?? '') !== '') ? $behaviorProjection['suggestion'] : null;
@endphp
<div x-show="layout === 'list'" x-cloak class="space-y-6">
    @if($insightMessage && $tab === 'tong-quan')
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 px-4 py-3 text-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Gợi ý</p>
            <p class="mt-1 text-gray-900 dark:text-white">{{ $insightMessage }}</p>
        </div>
    @endif
    @if(isset($coachingNarrative['today_message']) && $tab === 'tong-quan')
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 px-4 py-3 border-l-4 border-l-brand-500">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Hệ thống hôm nay muốn nói</p>
            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $coachingNarrative['today_message'] }}</p>
        </div>
    @endif
    @if($tab === 'tong-quan')
        <div id="tong-quan-panel" data-current-tab="tong-quan" data-partial-url="{{ route('cong-viec', ['tab' => 'tong-quan', 'partial' => 1]) }}">
            @include('pages.cong-viec.partials.tong-quan')
        </div>
    @elseif($tab === 'hom-nay')
        <div id="today-panel" data-current-tab="hom-nay" data-partial-url="{{ route('cong-viec', ['tab' => 'hom-nay', 'partial' => 1]) }}">
            @include('pages.cong-viec.partials.hom-nay')
        </div>
    @elseif($tab === 'du-kien')
        <div id="du-kien-panel" data-current-tab="du-kien" data-partial-url="{{ route('cong-viec', ['tab' => 'du-kien', 'partial' => 1]) }}">
            @include('pages.cong-viec.partials.du-kien')
        </div>
    @elseif($tab === 'hoan-thanh')
        <div id="hoan-thanh-panel" data-current-tab="hoan-thanh" data-partial-url="{{ route('cong-viec', ['tab' => 'hoan-thanh', 'partial' => 1]) }}">
            @include('pages.cong-viec.partials.hoan-thanh')
        </div>
    @endif
</div>
<div x-show="layout === 'board'" x-cloak class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Bảng Kanban</h2>
        <button type="button" x-show="!showAddTask" @click="addTaskKanbanStatus = 'backlog'; showAddTask = true" class="flex items-center gap-2 rounded-lg py-1.5 pl-1.5 pr-2 text-sm text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-red-500">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
            </span>
            <span>Thêm công việc</span>
        </button>
    </div>
    @include('pages.cong-viec.partials.kanban-board')
</div>
<div x-show="layout === 'calendar'" x-cloak class="mx-auto max-w-[600px] space-y-6 py-8">
    <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-300">Lịch</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400">Chế độ lịch sẽ hiển thị công việc theo ngày. Tính năng đang được phát triển.</p>
</div>
<div x-show="layout === 'focus'" x-cloak class="space-y-6">
    @if($tab === 'hom-nay' && isset($tasksToday) && $tasksToday->isNotEmpty())
        @php
            $focusList = (isset($focusPlan) && $focusPlan['focus']->isNotEmpty()) ? $focusPlan['focus'] : $tasksToday;
            $firstFocusInstance = $focusList->first();
            $secondFocusInstance = $focusList->get(1);
            $firstEstMin = app(\App\Services\TaskDurationLearningService::class)->getPredictedMinutes($firstFocusInstance->task->id) ?? $firstFocusInstance->task->estimated_duration ?? null;
        @endphp
        <p class="text-xs font-semibold uppercase tracking-wide text-amber-600 dark:text-amber-400">Tập trung — 1 việc mỗi lần</p>
        <ul class="space-y-1">
            @include('pages.cong-viec.partials.task-row', [
                'instance' => $firstFocusInstance,
                'task' => $firstFocusInstance->task,
                'toggleCompleteUrl' => route('cong-viec.instances.toggle-complete', $firstFocusInstance->id),
                'confirmCompleteUrl' => route('cong-viec.instances.confirm-complete', $firstFocusInstance->id),
                'completed' => false,
                'asTodayRow' => true,
                'streak' => isset($taskStreaks) ? ($taskStreaks[$firstFocusInstance->work_task_id] ?? null) : null,
                'priorityScore' => isset($todayPriorityScores[$firstFocusInstance->id]) ? $todayPriorityScores[$firstFocusInstance->id]['score'] : null,
                'showIntelligence' => true,
                'estimatedMinutes' => $firstEstMin,
            ])
        </ul>
        @if($secondFocusInstance)
            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400"><span class="font-medium text-gray-700 dark:text-gray-300">Tiếp theo:</span> {{ $secondFocusInstance->task->title }}</p>
        @endif
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">Chế độ Focus chỉ áp dụng cho tab Hôm nay. Chuyển sang tab Hôm nay để tập trung 1 việc tại một thời điểm.</p>
    @endif
</div>
@include('pages.cong-viec.partials.modals')
</div>
@endsection

@section('congViecRightColumn')
@if(request('tab') === 'hom-nay' && isset($behaviorProfile) && $behaviorProfile)
    @php
        $sidebarFocusWindow = '—';
        if (!empty($behaviorProfile['completion_by_hour']) && is_array($behaviorProfile['completion_by_hour'])) {
            $byHour = $behaviorProfile['completion_by_hour'];
            $max = 0; $peakStart = null; $peakEnd = null;
            foreach ($byHour as $h => $cnt) { if ($cnt > $max) { $max = $cnt; $peakStart = $h; $peakEnd = $h; } }
            if ($max > 0 && $peakStart !== null) {
                for ($h = $peakStart - 1; $h >= 0 && ($byHour[$h] ?? 0) >= $max * 0.5; $h--) { $peakStart = $h; }
                for ($h = $peakEnd + 1; $h < 24 && ($byHour[$h] ?? 0) >= $max * 0.5; $h++) { $peakEnd = $h; }
                $sidebarFocusWindow = sprintf('%02d:00–%02d:00', $peakStart, min(23, $peakEnd + 1));
            }
        }
    @endphp
    <div class="mb-6 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-800/50 px-4 py-3">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Gợi ý hành vi</p>
        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">Hồ sơ: {{ $behaviorProfile['profile_label'] ?? '—' }}</p>
        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">Giờ làm tốt nhất: <span class="font-medium">{{ $sidebarFocusWindow }}</span></p>
        @if(!empty($behaviorProfile['hints']))
            <p class="mt-2 text-xs font-medium text-gray-600 dark:text-gray-300">Gợi ý:</p>
            <ul class="mt-0.5 space-y-0.5 text-sm text-gray-700 dark:text-gray-300">
                @foreach($behaviorProfile['hints'] as $hint)
                    <li class="flex items-start gap-1.5"><span class="text-brand-500">•</span><span>{{ $hint }}</span></li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
@include('pages.cong-viec.partials.program-context-panel')
@endsection
