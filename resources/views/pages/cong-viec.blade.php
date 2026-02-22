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
<div x-data="congViecPage()" @cong-viec-layout.window="layout = $event.detail.layout; try { localStorage.setItem('congViecLayout', layout); } catch(e) {}" @open-add-task.window="showAddTask = true; addTaskKanbanStatus = $event.detail?.kanban_status || 'backlog'" @cong-viec-require-confirm.window="openConfirmCompleteModal($event.detail.taskId, $event.detail.payload, $event.detail.p)" @form-dropdown-close-others.window="const id = $event.detail; if (id !== 'more' && id !== 'labels' && id !== 'location' && id !== 'inbox') { showMoreOptions = false; showLabelsPanel = false; showLocationPanel = false; showInboxDropdown = false; }">
@if($tab === 'tong-quan' && isset($behaviorPolicy) && $behaviorPolicy && in_array($behaviorPolicy->mode, ['micro_goal', 'reduced_reminder']))
    <div id="policy-feedback-banner" class="mb-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 border-l-4 border-l-brand-500 pl-4 py-3 text-sm text-gray-900 dark:text-white" data-policy-mode="{{ $behaviorPolicy->mode }}">
        @if($behaviorPolicy->mode === 'micro_goal')
            <p class="mb-2">Đề xuất: Chế độ mục tiêu nhỏ — tập trung ít task hơn để ổn định nhịp.</p>
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
@php
    $insightMessage = (isset($behaviorProjection['suggestion']) && (string) ($behaviorProjection['suggestion'] ?? '') !== '') ? $behaviorProjection['suggestion'] : null;
@endphp
<div x-show="layout === 'list'" x-cloak class="space-y-6">
    @if($insightMessage && $tab === 'tong-quan')
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 px-4 py-3 text-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Insight</p>
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
        @include('pages.cong-viec.partials.tong-quan')
    @elseif($tab === 'hom-nay')
        @include('pages.cong-viec.partials.hom-nay')
    @elseif($tab === 'du-kien')
        @include('pages.cong-viec.partials.du-kien')
    @elseif($tab === 'hoan-thanh')
        @include('pages.cong-viec.partials.hoan-thanh')
    @endif
</div>
<div x-show="layout === 'board'" x-cloak class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Kanban</h2>
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
    <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-300">Calendar</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400">Chế độ lịch sẽ hiển thị công việc theo ngày. Tính năng đang được phát triển.</p>
</div>
@include('pages.cong-viec.partials.modals')
</div>
@endsection

@section('congViecRightColumn')
@include('pages.cong-viec.partials.program-context-panel')
@endsection
