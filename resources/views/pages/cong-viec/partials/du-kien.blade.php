{{-- Dự kiến: timeline theo ngày — trộn lặp/không lặp theo next execution --}}
<div class="w-full max-w-[880px] space-y-4" x-data="{}">
    <h2 class="text-lg font-bold text-gray-900 dark:text-white">Dự kiến</h2>
    <button type="button" x-show="!showAddTask" x-cloak @click="addTaskKanbanStatus = 'backlog'; showAddTask = true" class="flex items-center gap-2 rounded-lg py-2 text-left text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-red-500">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
        </span>
        <span class="text-sm">Thêm công việc</span>
    </button>
    @if(isset($tasksUpcoming) && $tasksUpcoming->isNotEmpty())
        <div class="space-y-6">
            @foreach($tasksUpcoming as $ymd => $dayRows)
                @php
                    $dayLabel = \Carbon\Carbon::parse($ymd)->format('d/m/Y');
                @endphp
                <section>
                    <h3 class="mb-2 text-sm font-bold text-gray-800 dark:text-gray-200">📅 {{ $dayLabel }}</h3>
                    <ul class="space-y-0.5">
                        @foreach($dayRows as $row)
                            @if(($row['kind'] ?? '') === 'recurring')
                                @include('pages.cong-viec.partials.du-kien-recurring-row', ['row' => $row, 'compact' => true])
                            @else
                                @include('pages.cong-viec.partials.du-kien-single-compact', ['row' => $row])
                            @endif
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    @else
        <p class="text-gray-500 dark:text-gray-400">Không có công việc dự kiến.</p>
    @endif
</div>
