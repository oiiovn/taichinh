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
    <div class="task-row-actions flex shrink-0 items-center justify-end gap-0.5 self-center min-w-[4.5rem] opacity-0 transition-opacity duration-150 group-hover/task:opacity-100" data-no-detail>
        <a href="{{ route('cong-viec', ['edit' => $task->id]) }}" data-edit-task-id="{{ $task->id }}" class="shrink-0 rounded-lg p-2 text-gray-500 hover:bg-gray-200 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-600 dark:hover:text-gray-200" title="Sửa">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
        </a>
        <button type="button" @click="openDeleteModal({{ $task->id }}, @js($task->title))" class="shrink-0 rounded-lg p-2 text-gray-500 hover:bg-red-100 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-900/30 dark:hover:text-red-400" title="Xoá">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
        </button>
    </div>
</li>
