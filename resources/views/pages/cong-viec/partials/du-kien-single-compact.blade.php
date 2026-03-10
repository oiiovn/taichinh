@php
    $inst = $row['instance'];
    $task = $inst->task;
    $toggleUrl = route('cong-viec.instances.toggle-complete', $inst->id);
    $confirmUrl = route('cong-viec.instances.confirm-complete', $inst->id);
    $timeStr = $task->due_time ? substr($task->due_time, 0, 5) : '—';
@endphp
<li class="group/task flex items-start gap-3 rounded-lg border border-transparent py-2 px-2 transition-colors hover:bg-gray-50 hover:border-gray-200 dark:hover:bg-gray-800/50 dark:hover:border-gray-700">
    <input type="checkbox" class="task-checkbox mt-0.5 h-5 w-5 shrink-0 appearance-none rounded-full border-2 border-gray-300 bg-transparent text-red-500 focus:ring-2 focus:ring-red-500 dark:border-gray-600 checked:border-red-500 checked:bg-red-500"
        data-task-id="{{ $task->id }}"
        data-url="{{ $toggleUrl }}"
        data-instance-id="{{ $inst->id }}"
        data-confirm-url="{{ $confirmUrl }}"
        data-due-date="{{ $task->due_date?->format('Y-m-d') }}"
        data-due-time="{{ $task->due_time ?? '' }}"
        data-program-id="{{ $task->program_id ?? '' }}">
    <span class="w-12 shrink-0 font-mono text-sm text-gray-500 dark:text-gray-400">{{ $timeStr }}</span>
    <div class="min-w-0 flex-1">
        <span class="font-medium text-gray-900 dark:text-white">{{ $task->title }}</span>
        @if($task->project)<span class="ml-2 text-xs text-gray-500">{{ $task->project->name }}</span>@endif
    </div>
    <a href="{{ route('cong-viec', ['edit' => $task->id]) }}" class="shrink-0 rounded-full p-1 text-gray-400 opacity-0 transition-opacity hover:bg-gray-200 group-hover/task:opacity-100 dark:hover:bg-gray-600" title="Sửa">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
    </a>
</li>
