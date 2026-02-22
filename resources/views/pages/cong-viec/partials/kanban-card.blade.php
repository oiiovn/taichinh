@php
    $borderClass = match($task->category) {
        'revenue' => 'border-l-4 border-l-green-500',
        'growth' => 'border-l-4 border-l-purple-500',
        'maintenance' => 'border-l-4 border-l-gray-500',
        default => 'border-l-4 border-l-gray-300 dark:border-l-gray-600',
    };
    $descExcerpt = $task->description_html ? \Illuminate\Support\Str::limit(strip_tags(html_entity_decode($task->description_html, ENT_QUOTES, 'UTF-8')), 60) : null;
@endphp
<div class="kanban-card group/task rounded-lg border border-gray-200 bg-white dark:border-gray-600 dark:bg-gray-800 shadow-sm p-3 {{ $borderClass }} cursor-grab active:cursor-grabbing"
     draggable="true"
     data-task-id="{{ $task->id }}"
     @dragstart="if (event.target.closest('[data-no-drag]')) { event.preventDefault(); return; } event.dataTransfer.setData('text/plain', '{{ $task->id }}'); event.dataTransfer.effectAllowed = 'move';">
    <div class="flex flex-wrap items-center gap-1">
        @if($task->category)
            <span class="inline-block rounded px-1.5 py-0.5 text-xs font-medium
                @if($task->category === 'revenue') bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300
                @elseif($task->category === 'growth') bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300
                @else bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300
                @endif">{{ $task->category_label }}</span>
        @endif
        @if($task->priority_label)
            <span class="inline-block rounded px-1.5 py-0.5 text-xs font-medium
                @if($task->priority === 1) bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300
                @elseif($task->priority === 2) bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300
                @elseif($task->priority === 3) bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300
                @else bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400
                @endif">{{ $task->priority_label }}</span>
        @endif
        @if($task->impact)
            <span class="inline-block rounded px-1 py-0.5 text-xs
                @if($task->impact === 'high') bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200
                @elseif($task->impact === 'medium') bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300
                @else text-gray-500 dark:text-gray-400
                @endif">{{ $task->impact_label }}</span>
        @endif
    </div>
    <p class="mt-1.5 font-medium text-gray-900 dark:text-white text-sm line-clamp-2">{{ $task->title }}</p>
    @if($descExcerpt)
        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 line-clamp-2">{{ $descExcerpt }}</p>
    @endif
    @if($task->project)
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $task->project->name }}</p>
    @endif
    @if($task->due_date)
        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
            Hạn: {{ $task->due_date->format('d/m/Y') }}{{ $task->due_time ? ' ' . $task->due_time : '' }}
            @if($task->is_overtime)
                <span class="text-red-600 dark:text-red-400 font-medium">(Quá hạn)</span>
            @endif
        </p>
    @endif
    @if($task->estimated_duration || $task->actual_duration !== null)
        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
            Ước: {{ (int) $task->estimated_duration }} phút
            @if($task->actual_duration !== null)
                · Thực: {{ $task->actual_duration }} phút
            @endif
        </p>
    @endif
    @if($task->labels->isNotEmpty())
        <div class="mt-1.5 flex flex-wrap gap-1">
            @foreach($task->labels as $label)
                <span class="inline-flex items-center gap-1 rounded-full border border-gray-200 dark:border-gray-600 px-1.5 py-0.5 text-xs text-gray-700 dark:text-gray-300">
                    <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ $label->color ?? '#6b7280' }}"></span>
                    <span>{{ $label->name }}</span>
                </span>
            @endforeach
        </div>
    @endif
    <div class="mt-0 flex items-center justify-end gap-0.5 overflow-hidden max-h-0 border-t border-transparent pt-0 opacity-0 transition-[max-height,opacity] duration-200 group-hover/task:max-h-10 group-hover/task:border-gray-100 group-hover/task:pt-2 group-hover/task:opacity-100 dark:group-hover/task:border-gray-700" data-no-drag>
        <a href="{{ route('cong-viec', ['edit' => $task->id]) }}" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300" title="Sửa">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
        </a>
        <button type="button" @click="openDeleteModal({{ $task->id }}, @js($task->title))" class="rounded p-1 text-gray-400 hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-900/30 dark:hover:text-red-400" title="Xoá">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
        </button>
    </div>
</div>
