{{-- Dự kiến: task chưa đến hạn (due_date > hôm nay) --}}
<div class="w-full max-w-[880px] space-y-4">
    <h2 class="text-lg font-bold text-gray-900 dark:text-white">Dự kiến</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400">Công việc chưa đến hạn, sắp xếp theo ngày.</p>
    @if(isset($tasksUpcoming) && $tasksUpcoming->isNotEmpty())
        <ul class="space-y-1">
            @foreach($tasksUpcoming as $task)
                @include('pages.cong-viec.partials.task-row', ['task' => $task, 'toggleCompleteUrl' => $toggleCompleteUrl, 'completed' => false, 'asTodayRow' => false])
            @endforeach
        </ul>
    @else
        <p class="text-gray-500 dark:text-gray-400">Không có công việc dự kiến.</p>
    @endif
    <button type="button" x-show="!showAddTask" x-cloak @click="addTaskKanbanStatus = 'backlog'; showAddTask = true" class="flex items-center gap-2 rounded-lg py-2 text-left text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-red-500">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
        </span>
        <span class="text-sm">Thêm công việc</span>
    </button>
</div>
