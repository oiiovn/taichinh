@php
    $inst = $row['instance'];
    $task = $inst->task;
    $more = $row['more'] ?? [];
    $moreCount = (int) ($row['more_count'] ?? 0);
    $repeatLabel = $task->repeat_label ?? 'Lặp';
    $toggleUrl = route('cong-viec.instances.toggle-complete', $inst->id);
    $confirmUrl = route('cong-viec.instances.confirm-complete', $inst->id);
    $timeStr = $task->due_time ? substr($task->due_time, 0, 5) : '—';
    $compact = $compact ?? false;
@endphp
<li class="group/task flex flex-col rounded-lg border border-transparent py-2 px-2 transition-colors hover:bg-gray-50 hover:border-gray-200 dark:hover:bg-gray-800/50 dark:hover:border-gray-700" x-data="{ open: false }">
    <div class="flex items-start gap-3">
        <input type="checkbox" class="task-checkbox mt-0.5 h-5 w-5 shrink-0 appearance-none rounded-full border-2 border-gray-300 bg-transparent text-red-500 focus:ring-2 focus:ring-red-500 dark:border-gray-600 checked:border-red-500 checked:bg-red-500"
            data-task-id="{{ $task->id }}"
            data-url="{{ $toggleUrl }}"
            data-instance-id="{{ $inst->id }}"
            data-confirm-url="{{ $confirmUrl }}"
            data-due-date="{{ $task->due_date?->format('Y-m-d') }}"
            data-due-time="{{ $task->due_time ?? '' }}"
            data-program-id="{{ $task->program_id ?? '' }}">
        @if($compact)
        <span class="w-12 shrink-0 font-mono text-sm text-gray-500 dark:text-gray-400">{{ $timeStr }}</span>
        @endif
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-semibold text-gray-900 dark:text-white">{{ $task->title }}</span>
                @if($task->repeat === 'daily')<span class="rounded px-1.5 py-0.5 text-xs font-medium bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300">🔁 Daily</span>
                @elseif($task->repeat === 'weekly')<span class="rounded px-1.5 py-0.5 text-xs font-medium bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300">🔁 Weekly</span>
                @elseif($task->repeat === 'monthly')<span class="rounded px-1.5 py-0.5 text-xs font-medium bg-teal-100 text-teal-800 dark:bg-teal-900/40 dark:text-teal-300">🔁 Monthly</span>
                @else<span class="rounded px-1.5 py-0.5 text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">🔁 {{ $repeatLabel }}</span>
                @endif
            </div>
            @if(!$compact)
            <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">
                <span class="font-medium">Next:</span> {{ $inst->instance_date->format('d/m') }}@if($timeStr !== '—') · {{ $timeStr }}@endif
            </p>
            @endif
            @if(!empty($row['horizon_until']) && $moreCount > 0)
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-500">Kéo dài đến {{ $row['horizon_until'] }}</p>
            @endif
            @if($moreCount > 0)
                <button type="button" @click="open = !open" class="mt-1 text-left text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">
                    <span x-show="!open">⋯ Xem các lần tiếp theo</span>
                    <span x-show="open" x-cloak>Thu gọn</span>
                </button>
            @endif
        </div>
        <div class="task-row-actions flex shrink-0 items-center justify-end gap-0.5 self-center min-w-[4.5rem] opacity-0 transition-opacity duration-150 group-hover/task:opacity-100" data-no-detail>
            <a href="{{ route('cong-viec', ['edit' => $task->id]) }}" data-edit-task-id="{{ $task->id }}" class="shrink-0 rounded-lg p-2 text-gray-500 hover:bg-gray-200 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-600 dark:hover:text-gray-200" title="Sửa">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
            </a>
            <button type="button" @click="openDeleteModal({{ $task->id }}, @js($task->title))" class="shrink-0 rounded-lg p-2 text-gray-500 hover:bg-red-100 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-900/30 dark:hover:text-red-400" title="Xoá">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
            </button>
        </div>
    </div>
    @if(count($more) > 0)
        <ul x-show="open" x-cloak class="mt-2 ml-9 space-y-1 border-l-2 border-gray-200 pl-3 dark:border-gray-600">
            @foreach($more as $m)
                <li class="text-sm text-gray-600 dark:text-gray-400">
                    {{ $m['date'] }}@if(!empty($m['time'])) · {{ $m['time'] }}@endif
                </li>
            @endforeach
            @if($moreCount > count($more))
                <li class="text-xs text-gray-500">… còn lịch đến {{ $row['horizon_until'] ?? 'sau' }}</li>
            @endif
        </ul>
    @endif
</li>
