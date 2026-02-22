{{-- Level 1 – Fragile: tối giản, một mục tiêu duy nhất, coaching mạnh, không nhiều số --}}
<div class="tong-quan-focus w-full max-w-[720px] space-y-6">
    @if(!empty($interfaceAdaptation['level_up_message']))
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 border-l-4 border-l-brand-500 pl-4 py-3 text-sm text-gray-900 dark:text-white">
            {{ $interfaceAdaptation['level_up_message'] }}
        </div>
    @endif
    <header class="text-center">
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">Một việc quan trọng nhất hôm nay</h1>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Chọn một việc và hoàn thành nó. Đó là đủ.</p>
    </header>
    @php $firstTask = $tasksToday->first(); @endphp
    <section class="rounded-2xl border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/50 p-6">
        @if($firstTask)
            @include('pages.cong-viec.partials.task-row', ['task' => $firstTask, 'toggleCompleteUrl' => $toggleCompleteUrl, 'completed' => false, 'asTodayRow' => true])
            <p class="mt-4 text-center text-xs text-gray-500 dark:text-gray-400">Hoàn thành xong việc này rồi mới xem thêm.</p>
        @else
            <p class="text-center text-gray-600 dark:text-gray-300">{{ $coachingNarrative['empty_today_copy'] ?? 'Hôm nay bạn chưa có cam kết. Thêm một việc để bắt đầu.' }}</p>
            <button type="button" x-show="!showAddTask" @click="addTaskKanbanStatus = 'backlog'; showAddTask = true" class="mx-auto mt-4 flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 dark:bg-brand-500 dark:hover:bg-brand-600">
                <span>+</span> Thêm một việc
            </button>
        @endif
    </section>
</div>
