@php
    $completed = $completed ?? false;
    $asTodayRow = $asTodayRow ?? true;
@endphp
<li class="group/task {{ $asTodayRow ? 'task-row' : '' }} flex items-start gap-3 rounded-lg border border-transparent py-2.5 px-2 transition-colors hover:bg-gray-50 hover:border-gray-200 dark:hover:bg-gray-800/50 dark:hover:border-gray-700" @if($asTodayRow) data-task-id="{{ $task->id }}" @endif>
    <input type="checkbox" class="task-checkbox mt-1.5 h-6 w-6 shrink-0 appearance-none rounded-full border-2 border-gray-300 bg-transparent text-red-500 focus:ring-2 focus:ring-red-500 focus:ring-offset-0 dark:border-gray-600 checked:border-red-500 checked:bg-red-500" data-task-id="{{ $task->id }}" data-url="{{ str_replace('__ID__', $task->id, $toggleCompleteUrl) }}" data-due-date="{{ $task->due_date?->format('Y-m-d') }}" data-due-time="{{ $task->due_time ?? '' }}" data-program-id="{{ $task->program_id ?? '' }}" @checked($completed)>
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
            <p class="font-semibold {{ $completed ? 'text-gray-500 line-through dark:text-gray-400' : 'text-gray-900 dark:text-white' }}">{{ $task->title }}</p>
            @if($task->program_id && $task->relationLoaded('program') && $task->program)
                <a href="{{ route('cong-viec.programs.show', $task->program->id) }}" class="rounded px-1.5 py-0.5 text-xs font-medium bg-brand-100 text-brand-700 dark:bg-brand-900/40 dark:text-brand-300">{{ $task->program->title }}</a>
            @endif
            @if($task->priority_label)
                <span class="rounded px-1.5 py-0.5 text-xs font-medium
                    @if($task->priority === 1) bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300
                    @elseif($task->priority === 2) bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300
                    @elseif($task->priority === 3) bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300
                    @else bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400
                    @endif">{{ $task->priority_label }}</span>
            @endif
            @if(!$completed && $task->internalized_at)<span class="text-xs text-gray-500 dark:text-gray-400" title="Đã nội tâm hoá">✓</span>@endif
        </div>
        @if($task->description_html && trim(strip_tags(html_entity_decode($task->description_html, ENT_QUOTES, 'UTF-8'))) !== '')
            <p class="mt-0.5 line-clamp-2 text-sm {{ $completed ? 'text-gray-400 dark:text-gray-500' : 'text-gray-500 dark:text-gray-400' }}">{{ \Illuminate\Support\Str::limit(strip_tags(html_entity_decode($task->description_html, ENT_QUOTES, 'UTF-8')), 120) }}</p>
        @endif
        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-gray-500 dark:text-gray-400">
            @if($task->project)<span>{{ $task->project->name }}</span>@endif
            @if($task->due_date)<span>Hạn: {{ $task->due_date->format('d/m/Y') }}{{ $task->due_time ? ' ' . $task->due_time : '' }}</span>@endif
            @if($task->repeat && $task->repeat !== 'none')<span title="Lặp: {{ $task->repeat_label }}">↻</span>@endif
            @if($task->labels->isNotEmpty())
                @foreach($task->labels->take(3) as $label)
                    <span class="inline-flex items-center gap-1 rounded-full border border-gray-200 dark:border-gray-600 px-1.5 py-0.5">
                        <span class="h-1.5 w-1.5 shrink-0 rounded-full" style="background-color: {{ $label->color ?? '#6b7280' }}"></span>
                        <span>{{ $label->name }}</span>
                    </span>
                @endforeach
                @if($task->labels->count() > 3)<span>+{{ $task->labels->count() - 3 }}</span>@endif
            @endif
        </div>
    </div>
    <div class="flex shrink-0 items-center gap-0 overflow-hidden max-w-0 opacity-0 transition-[max-width,opacity] duration-200 group-hover/task:max-w-[80px] group-hover/task:opacity-100 group-hover/task:overflow-visible">
        <a href="{{ route('cong-viec', ['edit' => $task->id]) }}" class="shrink-0 rounded-full p-1.5 text-gray-400 hover:bg-gray-200 hover:text-gray-700 dark:hover:bg-gray-600 dark:hover:text-gray-200" title="Sửa">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
        </a>
        <button type="button" @click="openDeleteModal({{ $task->id }}, @js($task->title))" class="shrink-0 rounded-full p-1.5 text-gray-400 hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-900/30 dark:hover:text-red-400" title="Xoá">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
        </button>
    </div>
</li>
