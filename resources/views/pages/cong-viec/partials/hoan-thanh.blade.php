{{-- Đã hoàn thành: instance-based, group theo ngày --}}
@php
    $groups = $completedInstancesGrouped ?? ['today' => collect(), 'yesterday' => collect(), 'this_week' => collect(), 'older' => collect()];
    $labels = ['today' => 'Hôm nay', 'yesterday' => 'Hôm qua', 'this_week' => 'Tuần này', 'older' => 'Trước đó'];
@endphp
<div class="w-full max-w-[880px] space-y-6">
    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Đã hoàn thành</h2>
    @php $hasAny = $groups['today']->isNotEmpty() || $groups['yesterday']->isNotEmpty() || $groups['this_week']->isNotEmpty() || $groups['older']->isNotEmpty(); @endphp
    @if($hasAny)
        @foreach(['today', 'yesterday', 'this_week', 'older'] as $key)
            @if($groups[$key]->isNotEmpty())
                <section>
                    <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $labels[$key] }}</h3>
                    <ul class="space-y-1">
                        @foreach($groups[$key] as $instance)
                            @include('pages.cong-viec.partials.task-row', ['instance' => $instance, 'task' => $instance->task, 'toggleCompleteUrl' => route('cong-viec.instances.toggle-complete', $instance->id), 'confirmCompleteUrl' => route('cong-viec.instances.confirm-complete', $instance->id), 'completed' => true, 'asTodayRow' => false])
                        @endforeach
                    </ul>
                </section>
            @endif
        @endforeach
    @else
        <p class="text-gray-500 dark:text-gray-400">Chưa có công việc nào hoàn thành.</p>
    @endif
</div>
