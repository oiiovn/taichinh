{{-- Đã hoàn thành --}}
<div class="w-full max-w-[880px] space-y-6">
    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Đã hoàn thành</h2>
    @if($tasksCompleted->isNotEmpty())
        <ul class="space-y-1">
            @foreach($tasksCompleted as $task)
                @include('pages.cong-viec.partials.task-row', ['task' => $task, 'toggleCompleteUrl' => $toggleCompleteUrl, 'completed' => true, 'asTodayRow' => false])
            @endforeach
        </ul>
    @else
        <p class="text-gray-500 dark:text-gray-400">Chưa có công việc nào hoàn thành.</p>
    @endif
</div>
