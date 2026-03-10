@php
    $inModal = $inModal ?? false;
    $task = $task ?? null;
    $returnTab = $returnTab ?? 'tong-quan';
    if (!$task) return;
    $priorityLabels = \App\Models\CongViecTask::PRIORITY_LABELS;
    $repeatLabels = \App\Models\CongViecTask::REPEAT_LABELS;
    $kanbanLabels = \App\Models\CongViecTask::KANBAN_STATUSES;
    $categoryLabels = \App\Models\CongViecTask::CATEGORIES;
    $impactLabels = \App\Models\CongViecTask::IMPACTS;
    $remindLabels = \App\Models\CongViecTask::REMIND_OPTIONS;
@endphp
<article class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800/80 overflow-hidden">
    <div class="px-5 py-5 md:px-6 md:py-6">
        @if($inModal)
            <p class="mb-3 text-sm">
                <a href="{{ route('cong-viec.tasks.show', $task->id) }}" class="text-brand-600 hover:underline dark:text-brand-400">Mở trang chi tiết →</a>
            </p>
        @endif
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $task->title }}</h1>
        @if($task->completed)
            <p class="mt-2 text-sm font-medium text-green-600 dark:text-green-400">✓ Đã hoàn thành</p>
        @endif

        {{-- Hạn & thời gian --}}
        <section class="mt-4">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Hạn & thời gian</h2>
            <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-700 dark:text-gray-300">
                @if($task->due_date)
                    <span>Hạn: {{ $task->due_date->format('d/m/Y') }}{{ $task->due_time ? ' ' . substr($task->due_time, 0, 5) : '' }}</span>
                @else
                    <span class="text-gray-500">Chưa đặt hạn</span>
                @endif
                @if($task->available_after || $task->available_before)
                    @if($task->available_after)<span>Thực hiện sau: {{ substr($task->available_after, 0, 5) }}</span>@endif
                    @if($task->available_before)<span>Trước: {{ substr($task->available_before, 0, 5) }}</span>@endif
                @endif
            </div>
        </section>

        {{-- Ưu tiên, lặp, nhắc --}}
        <section class="mt-4">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Thiết lập</h2>
            <div class="mt-1.5 flex flex-wrap gap-2 text-sm">
                @if($task->priority && isset($priorityLabels[$task->priority]))
                    <span class="rounded px-2 py-0.5 font-medium {{ $task->priority <= 2 ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">{{ $priorityLabels[$task->priority] }}</span>
                @endif
                @if($task->repeat && ($task->repeat ?? 'none') !== 'none')
                    <span class="rounded bg-gray-100 px-2 py-0.5 dark:bg-gray-700 dark:text-gray-300">{{ $repeatLabels[$task->repeat] ?? $task->repeat }}{{ $task->repeat_interval > 1 ? ' (mỗi ' . $task->repeat_interval . ')' : '' }}</span>
                    @if($task->repeat_until)<span class="text-gray-600 dark:text-gray-400">Đến {{ $task->repeat_until->format('d/m/Y') }}</span>@endif
                @endif
                @if($task->remind_minutes_before !== null && array_key_exists($task->remind_minutes_before, $remindLabels))
                    <span class="text-gray-600 dark:text-gray-400">Nhắc: {{ $remindLabels[$task->remind_minutes_before] }}</span>
                @endif
                @if($task->estimated_duration)
                    <span class="text-gray-600 dark:text-gray-400">Ước lượng: {{ $task->estimated_duration }} phút</span>
                @endif
                @if($task->actual_duration)
                    <span class="text-gray-600 dark:text-gray-400">Thực tế: {{ $task->actual_duration }} phút</span>
                @endif
            </div>
        </section>

        {{-- Trạng thái Kanban, thể loại, tác động --}}
        <section class="mt-4">
            <div class="flex flex-wrap gap-2 text-sm">
                @if($task->kanban_status && isset($kanbanLabels[$task->kanban_status]))
                    <span class="rounded bg-gray-100 px-2 py-0.5 dark:bg-gray-700 dark:text-gray-300">{{ $kanbanLabels[$task->kanban_status] }}</span>
                @endif
                @if($task->category && isset($categoryLabels[$task->category]))
                    <span class="rounded bg-gray-100 px-2 py-0.5 dark:bg-gray-700 dark:text-gray-300">{{ $categoryLabels[$task->category] }}</span>
                @endif
                @if($task->impact && isset($impactLabels[$task->impact]))
                    <span class="rounded bg-gray-100 px-2 py-0.5 dark:bg-gray-700 dark:text-gray-300">Tác động: {{ $impactLabels[$task->impact] }}</span>
                @endif
            </div>
        </section>

        {{-- Nhãn --}}
        @if($task->labels && $task->labels->isNotEmpty())
            <section class="mt-4">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nhãn</h2>
                <div class="mt-1.5 flex flex-wrap gap-1.5">
                    @foreach($task->labels as $label)
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" style="background-color: {{ $label->color ?? '#6b7280' }}20; color: {{ $label->color ?? '#6b7280' }};">{{ $label->name }}</span>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Dự án & chương trình --}}
        @if($task->project || $task->program)
            <section class="mt-4">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Dự án & chương trình</h2>
                <div class="mt-1.5 space-y-0.5 text-sm text-gray-700 dark:text-gray-300">
                    @if($task->project)<p>Dự án: {{ $task->project->name }}</p>@endif
                    @if($task->program)<p>Chương trình: {{ $task->program->title }}</p>@endif
                </div>
            </section>
        @endif

        {{-- Địa điểm --}}
        @if($task->location && trim($task->location) !== '')
            <section class="mt-4">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Địa điểm</h2>
                <p class="mt-1.5 text-sm text-gray-700 dark:text-gray-300">{{ $task->location }}</p>
            </section>
        @endif

        {{-- Liên kết lịch thanh toán --}}
        @if($task->meta && !empty($task->meta['payment_schedule_id']))
            <section class="mt-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Liên kết</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Từ lịch thanh toán #{{ $task->meta['payment_schedule_id'] }}</p>
            </section>
        @endif

        {{-- Nội tâm hóa --}}
        @if($task->internalized_at)
            <section class="mt-4">
                <p class="text-xs text-gray-500 dark:text-gray-400">Đã nội tâm hóa lúc {{ $task->internalized_at->format('d/m/Y H:i') }}</p>
            </section>
        @endif

        {{-- Mô tả --}}
        @if($task->description_html && strip_tags($task->description_html) !== '')
            <section class="mt-5 border-t border-gray-200 dark:border-gray-600 pt-4">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Mô tả</h2>
                <div class="mt-2 prose prose-sm max-w-none dark:prose-invert text-gray-700 dark:text-gray-300">
                    {!! $task->description_html !!}
                </div>
            </section>
        @endif

        {{-- Hành động --}}
        <div class="mt-6 flex flex-wrap items-center gap-2">
            @if(!$inModal)
                <a href="{{ route('cong-viec', ['edit' => $task->id, 'tab' => $returnTab]) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">Sửa</a>
                @if(!$task->completed)
                    <form action="{{ route('cong-viec.tasks.toggle-complete', $task->id) }}" method="POST" class="inline" onsubmit="return confirm('Đánh dấu đã hoàn thành?');">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-700">Hoàn thành</button>
                    </form>
                @endif
                <form id="form-delete-task-{{ $task->id }}" action="{{ route('cong-viec.tasks.destroy', $task->id) }}" method="POST" class="inline" onsubmit="return confirm('Xóa công việc này? Không thể hoàn tác.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-700 shadow-sm hover:bg-red-50 dark:border-red-800 dark:bg-gray-700 dark:text-red-400 dark:hover:bg-red-900/20">Xóa</button>
                </form>
            @endif
        </div>
    </div>
</article>
