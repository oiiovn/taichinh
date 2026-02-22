{{-- Level 4 – Mastery: multi-program, projection nâng cao, control panel chiến lược --}}
@php
    $radar = $behaviorRadar ?? [];
    $trust = isset($radar['trust_global']) && $radar['trust_global'] !== null ? round($radar['trust_global'] * 100) : null;
    $cli = $radar['cli'] ?? null;
    $cliLabel = $cli !== null ? ($cli > 0.7 ? 'Ổn định' : ($cli > 0.4 ? 'Bình thường' : 'Quá tải')) : null;
    $proj60 = isset($radar['projection_60d']) && $radar['projection_60d'] !== null ? round($radar['projection_60d'] * 100) : null;
    $proj90 = (isset($behaviorProjection) && is_array($behaviorProjection) && isset($behaviorProjection['probability_maintain_90d'])) ? round($behaviorProjection['probability_maintain_90d'] * 100) : null;
    $modeLabel = isset($radar['mode']) ? ($radar['mode'] === 'micro_goal' ? 'Mục tiêu nhỏ' : ($radar['mode'] === 'reduced_reminder' ? 'Giảm nhắc' : 'Bình thường')) : 'Bình thường';
    $todayRemain = $tasksToday->count();
    $todayProgDone = $todayProgramTaskDone ?? 0;
    $todayProgTotal = $todayProgramTaskTotal ?? 0;
@endphp
<div class="tong-quan-strategic w-full max-w-[1200px] space-y-6">
    @if(!empty($interfaceAdaptation['level_up_message']))
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 border-l-4 border-l-brand-500 pl-4 py-3 text-sm text-gray-900 dark:text-white">
            {{ $interfaceAdaptation['level_up_message'] }}
        </div>
    @endif
    <header class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">Tổng quan — Chế độ chiến lược</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Đa chương trình, projection và điều chỉnh tối ưu.</p>
        </div>
    </header>
    <section class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800/60"><p class="text-xs uppercase text-gray-400 dark:text-gray-500">Trust</p><p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $trust ?? '—' }}%</p></div>
        @if($cliLabel !== null)<div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800/60"><p class="text-xs uppercase text-gray-400 dark:text-gray-500">CLI</p><p class="text-lg font-bold text-gray-900 dark:text-white">{{ $cliLabel }}</p></div>@endif
        @if($proj60 !== null)<div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800/60"><p class="text-xs uppercase text-gray-400 dark:text-gray-500">Duy trì 60d</p><p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $proj60 }}%</p></div>@endif
        @if($proj90 !== null)<div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800/60"><p class="text-xs uppercase text-gray-400 dark:text-gray-500">Duy trì 90d</p><p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $proj90 }}%</p></div>@endif
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800/60"><p class="text-xs uppercase text-gray-400 dark:text-gray-500">Chế độ</p><p class="text-lg font-bold text-gray-900 dark:text-white">{{ $modeLabel }}</p></div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800/60"><p class="text-xs uppercase text-gray-400 dark:text-gray-500">Còn hôm nay</p><p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $todayRemain }}</p></div>
        @if($todayProgTotal > 0)<div class="rounded-xl border border-brand-200 bg-brand-50/50 p-4 dark:border-brand-800 dark:bg-brand-900/20"><p class="text-xs uppercase text-brand-600 dark:text-brand-400">Program</p><p class="text-2xl font-bold text-gray-900 dark:text-white"><span id="program-today-done">{{ $todayProgDone }}</span>/<span id="program-today-total">{{ $todayProgTotal }}</span></p></div>@endif
    </section>
    <section class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Chương trình</h2>
            @include('pages.cong-viec.partials.active-program-card')
            @if(isset($userPrograms) && $userPrograms->count() > 1)
                <p class="text-sm text-gray-500 dark:text-gray-400"><a href="{{ route('cong-viec.programs.index') }}" class="text-brand-600 hover:underline dark:text-brand-400">Xem tất cả {{ $userPrograms->count() }} chương trình</a></p>
            @endif
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800/30">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-3">Thực thi hôm nay</h2>
            @if($tasksToday->isNotEmpty())
                <ul class="space-y-1" id="today-task-list">
                    @foreach($tasksToday as $task)
                        @include('pages.cong-viec.partials.task-row', ['task' => $task, 'toggleCompleteUrl' => $toggleCompleteUrl, 'completed' => false, 'asTodayRow' => true])
                    @endforeach
                </ul>
            @else
                <p class="text-gray-500 dark:text-gray-400">{{ $coachingNarrative['empty_today_copy'] ?? 'Hôm nay bạn chưa có cam kết nào. Thêm một việc để bắt đầu.' }}</p>
            @endif
            <button type="button" x-show="!showAddTask" x-cloak @click="addTaskKanbanStatus = 'backlog'; showAddTask = true" class="mt-3 flex items-center gap-2 text-sm text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">+ Thêm công việc</button>
        </div>
    </section>
</div>
