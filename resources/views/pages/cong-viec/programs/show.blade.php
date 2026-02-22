@extends('layouts.cong-viec')

@section('congViecContent')
<div class="mx-auto max-w-2xl">
    <div class="mb-6 flex items-center justify-between">
        <a href="{{ route('cong-viec.programs.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">← Chương trình</a>
    </div>
    <h1 class="mb-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $program->title }}</h1>
    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">
        {{ $program->start_date->format('d/m/Y') }} → {{ $program->getEndDateResolved()->format('d/m/Y') }} · {{ $program->getDaysTotal() }} ngày
        · Mỗi ngày {{ $program->daily_target_count ?? 1 }} · {{ \App\Models\BehaviorProgram::ESCALATION_RULES[$program->escalation_rule] ?? $program->escalation_rule }}
    </p>

    @if(!empty($progress))
        <div class="mb-6 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
            <h2 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Tiến độ</h2>
            <div class="grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
                <div>
                    <p class="text-gray-500 dark:text-gray-400">Ngày đã qua</p>
                    <p class="font-semibold text-gray-900 dark:text-white">{{ $progress['days_elapsed'] ?? 0 }}/{{ $progress['days_total'] ?? 0 }}</p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400">Ngày đạt target</p>
                    <p class="font-semibold text-gray-900 dark:text-white">{{ $progress['days_with_completion'] ?? 0 }}</p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400">Tỷ lệ hoàn thành</p>
                    <p class="font-semibold text-gray-900 dark:text-white">{{ isset($progress['completion_rate']) ? round($progress['completion_rate'] * 100) . '%' : '—' }}</p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400">Điểm nhất quán</p>
                    <p class="font-semibold text-gray-900 dark:text-white">{{ isset($progress['integrity_score']) ? round($progress['integrity_score'] * 100) . '%' : '—' }}</p>
                </div>
            </div>
        </div>
    @endif

    <div class="rounded-lg border border-gray-200 dark:border-gray-700">
        <h2 class="border-b border-gray-200 px-4 py-3 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-300">Công việc trong chương trình</h2>
        @if($tasks->isEmpty())
            <p class="px-4 py-6 text-sm text-gray-500 dark:text-gray-400">Chưa có công việc nào gắn với chương trình này. Khi tạo/sửa công việc, chọn chương trình để gắn.</p>
            <p class="px-4 pb-4"><a href="{{ route('cong-viec') }}" class="text-brand-600 hover:underline dark:text-brand-400">Đi tới Công việc →</a></p>
        @else
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($tasks as $t)
                    <li class="flex items-center justify-between px-4 py-3">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $t->title }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $t->due_date ? $t->due_date->format('d/m/Y') : '—' }}
                                {{ $t->completed ? '· Đã xong' : '' }}
                            </p>
                        </div>
                        <a href="{{ route('cong-viec', ['edit' => $t->id]) }}" class="text-sm text-brand-600 hover:underline dark:text-brand-400">Sửa</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
