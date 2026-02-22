{{-- Level 2 – Stabilizing: tiến độ chương trình, 1–2 insight, gợi ý nhẹ --}}
@php
    $radar = $behaviorRadar ?? [];
    $trust = isset($radar['trust_global']) && $radar['trust_global'] !== null ? round($radar['trust_global'] * 100) : null;
    $proj60 = isset($radar['projection_60d']) && $radar['projection_60d'] !== null ? round($radar['projection_60d'] * 100) : null;
    $todayRemain = $tasksToday->count();
    $config = $interfaceAdaptation['config'] ?? [];
    $showKpi = $config['show_kpi_count'] ?? 2;
@endphp
<div class="tong-quan-guided w-full max-w-[880px] space-y-6">
    @if(!empty($interfaceAdaptation['level_up_message']))
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 border-l-4 border-l-brand-500 pl-4 py-3 text-sm text-gray-900 dark:text-white">
            {{ $interfaceAdaptation['level_up_message'] }}
        </div>
    @endif
    <header>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">Tổng quan</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Tiến độ và gợi ý nhẹ.</p>
    </header>
    @if($showKpi >= 2)
        <section class="grid grid-cols-2 gap-3">
            @if($trust !== null)<div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800/60"><p class="text-xs text-gray-400 dark:text-gray-500">Trust</p><p class="text-xl font-bold text-gray-900 dark:text-white">{{ $trust }}%</p></div>@endif
            @if($proj60 !== null)<div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800/60"><p class="text-xs text-gray-400 dark:text-gray-500">Duy trì 60d</p><p class="text-xl font-bold text-gray-900 dark:text-white">{{ $proj60 }}%</p></div>@endif
        </section>
    @endif
    <section class="rounded-xl border border-gray-200 bg-gray-50/50 p-5 dark:border-gray-700 dark:bg-gray-800/30">
        @include('pages.cong-viec.partials.active-program-card')
    </section>
    <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800/30">
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
        <button type="button" x-show="!showAddTask" x-cloak @click="addTaskKanbanStatus = 'backlog'; showAddTask = true" class="mt-3 flex items-center gap-2 rounded-lg py-2 text-left text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-red-500">+</span>
            <span class="text-sm">Thêm công việc</span>
        </button>
    </section>
</div>
