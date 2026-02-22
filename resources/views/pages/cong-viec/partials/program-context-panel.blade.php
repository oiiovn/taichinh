@php
    $narrative = $coachingNarrative['sidebar_narrative'] ?? null;
@endphp
@if($narrative)
    <div class="space-y-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $narrative['phase'] ?? '' }}</p>
        <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $narrative['headline'] ?? 'Điều quan trọng hôm nay' }}</h4>
        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $narrative['body'] ?? '' }}</p>
        @if(!empty($narrative['cta_url']))
            <a href="{{ $narrative['cta_url'] }}" class="inline-block text-sm font-medium text-brand-600 hover:underline dark:text-brand-400">{{ $narrative['cta'] ?? 'Xem thêm' }} →</a>
        @endif
    </div>
@else
    @if(isset($activeProgram) && $activeProgram && isset($activeProgramProgress) && $activeProgramProgress)
        @php
            $prog = $activeProgramProgress;
            $daysElapsed = $prog['days_elapsed'] ?? 0;
            $daysTotal = $prog['days_total'] ?? 30;
            $daysLeft = max(0, $daysTotal - $daysElapsed);
            $integrityPct = isset($prog['integrity_score']) ? round($prog['integrity_score'] * 100) : 0;
        @endphp
        <div class="space-y-4">
            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Chương trình đang chạy</h4>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Tiến độ</span>
                    <span class="font-medium text-gray-900 dark:text-white"><span id="sidebar-program-elapsed">{{ $daysElapsed }}</span>/{{ $daysTotal }} ngày</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Còn lại</span>
                    <span class="font-medium" id="sidebar-program-days-left">{{ $daysLeft }} ngày</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Integrity</span>
                    <span class="font-medium" id="sidebar-program-integrity">{{ $integrityPct }}%</span>
                </div>
                <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                    <a href="{{ route('cong-viec.programs.show', $activeProgram->id) }}" class="text-brand-600 hover:underline dark:text-brand-400">Xem hành trình →</a>
                </div>
            </div>
        </div>
    @else
        <div class="space-y-3 text-sm text-gray-500 dark:text-gray-400">
            <p>Chưa có chương trình active. Tạo hành trình để theo dõi tiến độ.</p>
            <a href="{{ route('cong-viec.programs.index') }}" class="inline-block text-brand-600 hover:underline dark:text-brand-400">Chương trình</a>
        </div>
    @endif
@endif
