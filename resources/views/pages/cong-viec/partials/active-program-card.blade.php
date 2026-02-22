@if(isset($activeProgram) && $activeProgram && isset($activeProgramProgress) && $activeProgramProgress)
@php
    $prog = $activeProgramProgress;
    $daysElapsed = $prog['days_elapsed'] ?? 0;
    $daysTotal = $prog['days_total'] ?? 30;
    $integrityPct = isset($prog['integrity_score']) ? round($prog['integrity_score'] * 100) : 0;
    $todayDone = $todayProgramTaskDone ?? 0;
    $todayTotal = $todayProgramTaskTotal ?? 0;
    $intInterpretation = $coachingNarrative['integrity_interpretation'] ?? null;
@endphp
<div class="rounded-xl border border-brand-200 bg-brand-50/50 p-5 dark:border-brand-800 dark:bg-brand-900/20" id="active-program-card" data-program-id="{{ $activeProgram->id }}">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $activeProgram->title }}</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Ngày {{ $daysElapsed }} / {{ $daysTotal }}</p>
            <p class="mt-0.5 text-sm font-medium text-brand-600 dark:text-brand-400">Integrity: <span id="program-integrity-value">{{ $integrityPct }}</span>% @if(!empty($intInterpretation['label'])) <span class="font-normal text-gray-600 dark:text-gray-400">— {{ $intInterpretation['label'] }}{{ !empty($intInterpretation['risk']) ? ' · Rủi ro ' . $intInterpretation['risk'] : '' }}</span>@endif</p>
            @if(!empty($intInterpretation['hint']))
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $intInterpretation['hint'] }}</p>
            @endif
            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Hôm nay: <span id="program-today-done">{{ $todayDone }}</span>/<span id="program-today-total">{{ $todayTotal }}</span> mục tiêu</p>
        </div>
        <a href="{{ route('cong-viec.programs.show', $activeProgram->id) }}" class="shrink-0 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm font-medium text-gray-900 dark:text-white hover:bg-gray-50 dark:hover:bg-gray-700">Chi tiết</a>
    </div>
    <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
        <div id="program-progress-bar" class="h-full rounded-full bg-brand-500 transition-all duration-500 dark:bg-brand-400" style="width: {{ $daysTotal > 0 ? min(100, round(($daysElapsed / $daysTotal) * 100)) : 0 }}%;"></div>
    </div>
</div>
@else
<div class="rounded-xl border border-dashed border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-6 text-center">
    <p class="text-sm text-gray-600 dark:text-gray-400">Bạn chưa có chương trình nào.</p>
    <a href="{{ route('cong-viec.programs.index') }}" class="mt-3 inline-block rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 dark:bg-brand-500 dark:hover:bg-brand-600">Tạo hành trình 30 ngày</a>
</div>
@endif
