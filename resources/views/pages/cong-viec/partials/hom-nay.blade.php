{{-- Hôm nay: chỉ thực thi hôm nay --}}
<div class="w-full max-w-[880px] space-y-4">
    <h2 class="text-lg font-bold text-gray-900 dark:text-white">Hôm nay</h2>
    @if($tasksToday->isNotEmpty())
        <ul class="space-y-1" id="today-task-list">
            @foreach($tasksToday as $task)
                @include('pages.cong-viec.partials.task-row', ['task' => $task, 'toggleCompleteUrl' => $toggleCompleteUrl, 'completed' => false, 'asTodayRow' => true])
            @endforeach
        </ul>
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
