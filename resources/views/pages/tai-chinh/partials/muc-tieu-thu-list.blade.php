@php
    $goals = $incomeGoals ?? collect();
    $summary = $incomeGoalSummary ?? ['goals' => []];
    $summaryByKey = collect($summary['goals'] ?? [])->keyBy('name');
@endphp
<div id="muc-tieu-thu-list-wrap" data-ajax-container data-count="{{ $goals->count() }}">
    @if($goals->isEmpty())
        <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-6 dark:border-gray-700 dark:bg-gray-800/50">
            <p class="text-gray-600 dark:text-gray-400">Nhấn <strong>+ Thêm mục tiêu thu</strong> để tạo mục tiêu đầu tiên.</p>
        </div>
    @else
        <ul class="space-y-4">
            @foreach($goals as $g)
                @php
                    $row = $summaryByKey->get($g->name);
                    $earned = $row['earned_vnd'] ?? 0;
                    $target = $g->amount_target_vnd;
                    $achievementPct = $row['achievement_pct'] ?? ($target > 0 ? round(($earned / $target) * 100, 1) : 0);
                    $met = $row['met'] ?? ($earned >= $target);
                    $streak = $row['achievement_streak'] ?? 0;
                    $periodLabel = $g->period_type === 'month' && $g->year && $g->month ? 'T' . $g->month . '/' . $g->year : ($g->period_type === 'custom' && $g->period_start && $g->period_end ? \Carbon\Carbon::parse($g->period_start)->format('d/m') . ' – ' . \Carbon\Carbon::parse($g->period_end)->format('d/m/Y') : 'Tháng hiện tại');
                    $barPct = min(100, $achievementPct);
                    $barColor = $met ? 'bg-emerald-500' : ($achievementPct >= 80 ? 'bg-amber-500' : 'bg-gray-400');
                @endphp
                <li class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800/50 dark:text-white">
                    <div class="flex items-center gap-2">
                        <p class="min-w-0 flex-1 text-theme-sm text-gray-700 dark:text-gray-300">
                            <span class="font-medium text-gray-900 dark:text-white">{{ $g->name }}</span>
                            · Kỳ {{ $periodLabel }} · Mục tiêu {{ number_format($target) }} ₫
                            · Đã thu <span class="{{ $met ? 'text-emerald-600 dark:text-emerald-400 font-medium' : '' }}">{{ number_format($earned) }} ₫</span>
                            <span class="text-theme-xs">({{ $achievementPct }}%)</span>
                            @if($met)
                                <span class="text-theme-xs font-medium text-emerald-600 dark:text-emerald-400"> · Đạt mục tiêu</span>
                                @if($streak > 0) <span class="text-theme-xs text-gray-500 dark:text-gray-400"> · {{ $streak }} kỳ</span> @endif
                            @else
                                <span class="text-theme-xs text-gray-500 dark:text-gray-400"> · Chưa đạt</span>
                            @endif
                        </p>
                    </div>
                    <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                        <div class="h-full rounded-full {{ $barColor }}" style="width: {{ $barPct }}%;"></div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
