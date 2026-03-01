@php
    $thresholds = $budgetThresholds ?? collect();
    $incomeGoals = $incomeGoals ?? collect();
    $summary = $budgetThresholdSummary ?? ['thresholds' => [], 'aggregate' => []];
    $summaryItems = $summary['thresholds'] ?? [];
    $summaryByKey = collect($summaryItems)->keyBy(fn ($row) => $row['threshold_id'] ?? $row['name'] ?? null);
    $goalSummaryByKey = collect($incomeGoalSummary['goals'] ?? [])->keyBy(fn ($row) => $row['goal_id'] ?? $row['name'] ?? null);
    $now = now();
    $filterPct = $filter_pct ?? '';
    $filterVuot = $filter_vuot ?? '';
    $filterHetHan = $filter_het_han ?? '';
    $filterKy = $filter_ky ?? '';
    $filterKyParts = [];
    if (preg_match('/^(\d{4})-(\d{1,2})$/', $filterKy, $m)) {
        $filterKyParts = ['year' => (int) $m[1], 'month' => (int) $m[2]];
    }

    $filteredThresholds = $thresholds->filter(function ($t) use ($summaryByKey, $now, $filterPct, $filterVuot, $filterHetHan, $filterKyParts) {
        $row = $summaryByKey->get($t->id) ?? $summaryByKey->get($t->name);
        $spent = $row['spent_vnd'] ?? 0;
        $limit = $t->amount_limit_vnd;
        $breached = $row['breached'] ?? false;
        $pct = $limit > 0 ? round(($spent / $limit) * 100, 1) : 0;
        $expired = false;
        $periodEndInPastMonth = false;
        if ($t->period_type === 'month' && $t->year && $t->month) {
            $expired = $t->year < $now->year || ($t->year == $now->year && $t->month < $now->month);
            $periodEndInPastMonth = $expired;
        } elseif ($t->period_type === 'custom' && $t->period_end) {
            $periodEnd = \Carbon\Carbon::parse($t->period_end)->endOfDay();
            $expired = $periodEnd->lt($now);
            $periodEndInPastMonth = $periodEnd->lt($now->copy()->startOfMonth());
        }
        if ($filterVuot === '1' && !$breached) return false;
        if ($filterHetHan === '1') { if (!$periodEndInPastMonth) return false; } else { if ($periodEndInPastMonth) return false; }
        if (!empty($filterKyParts)) {
            if ($t->period_type === 'month' && $t->year && $t->month) {
                if ((int) $t->year !== $filterKyParts['year'] || (int) $t->month !== $filterKyParts['month']) return false;
            } elseif ($t->period_type === 'custom' && $t->period_start && $t->period_end) {
                $start = \Carbon\Carbon::parse($t->period_start)->startOfDay();
                $end = \Carbon\Carbon::parse($t->period_end)->endOfDay();
                $kyStart = \Carbon\Carbon::create($filterKyParts['year'], $filterKyParts['month'], 1)->startOfDay();
                $kyEnd = $kyStart->copy()->endOfMonth();
                if ($end->lt($kyStart) || $start->gt($kyEnd)) return false;
            } else {
                return false;
            }
        }
        if ($filterPct !== '') {
            if ($filterPct === 'under60' && $pct >= 60) return false;
            if ($filterPct === '60_80' && ($pct < 60 || $pct >= 80)) return false;
            if ($filterPct === '80_100' && ($pct < 80 || $pct > 100)) return false;
            if ($filterPct === 'over100' && $pct <= 100) return false;
        }
        return true;
    })->map(function ($t) use ($now, $summaryByKey) {
        $expired = false;
        $sortStart = '0000-00-00';
        $sortEnd = '9999-12-31';
        if ($t->period_type === 'month' && $t->year && $t->month) {
            $start = \Carbon\Carbon::create($t->year, $t->month, 1);
            $sortStart = $start->format('Y-m-d');
            $sortEnd = $start->copy()->endOfMonth()->format('Y-m-d');
            $expired = $t->year < $now->year || ($t->year == $now->year && $t->month < $now->month);
        } elseif ($t->period_type === 'custom' && $t->period_start && $t->period_end) {
            $start = \Carbon\Carbon::parse($t->period_start);
            $end = \Carbon\Carbon::parse($t->period_end)->endOfDay();
            $sortStart = $start->format('Y-m-d');
            $sortEnd = $end->format('Y-m-d');
            $expired = $end->lt($now);
        }
        $row = $summaryByKey->get($t->id) ?? $summaryByKey->get($t->name);
        $breached = $row['breached'] ?? false;
        $limit = $t->amount_limit_vnd ?? 0;
        $sortKey = (int) \Carbon\Carbon::parse($sortEnd)->format('Ymd');
        return (object)['type' => 'threshold', 'item' => $t, '_expired' => $expired, '_start' => $sortStart, '_end' => $sortEnd, '_sort_key' => $sortKey, '_flag' => $breached ? 1 : 0, '_limit' => $limit];
    })->values();

    $filteredGoals = $incomeGoals->filter(function ($g) use ($goalSummaryByKey, $now, $filterPct, $filterVuot, $filterHetHan, $filterKyParts) {
        $row = $goalSummaryByKey->get($g->id) ?? $goalSummaryByKey->get($g->name);
        $target = $g->amount_target_vnd;
        $earned = $row['earned_vnd'] ?? 0;
        $achievementPct = $target > 0 ? round(($earned / $target) * 100, 1) : 0;
        $met = $row['met'] ?? ($earned >= $target);
        $expired = false;
        if ($g->period_type === 'month' && $g->year && $g->month) {
            $expired = $g->year < $now->year || ($g->year == $now->year && $g->month < $now->month);
        } elseif ($g->period_type === 'custom' && $g->period_end) {
            $expired = \Carbon\Carbon::parse($g->period_end)->endOfDay()->lt($now);
        }
        if ($filterVuot === '1' && !$met) return false;
        if ($filterHetHan === '1') { if (!$expired) return false; } else { if ($expired) return false; }
        if (!empty($filterKyParts)) {
            if ($g->period_type === 'month' && $g->year && $g->month) {
                if ((int) $g->year !== $filterKyParts['year'] || (int) $g->month !== $filterKyParts['month']) return false;
            } elseif ($g->period_type === 'custom' && $g->period_start && $g->period_end) {
                $start = \Carbon\Carbon::parse($g->period_start)->startOfDay();
                $end = \Carbon\Carbon::parse($g->period_end)->endOfDay();
                $kyStart = \Carbon\Carbon::create($filterKyParts['year'], $filterKyParts['month'], 1)->startOfDay();
                $kyEnd = $kyStart->copy()->endOfMonth();
                if ($end->lt($kyStart) || $start->gt($kyEnd)) return false;
            } else {
                return false;
            }
        }
        if ($filterPct !== '') {
            if ($filterPct === 'under60' && $achievementPct >= 60) return false;
            if ($filterPct === '60_80' && ($achievementPct < 60 || $achievementPct >= 80)) return false;
            if ($filterPct === '80_100' && ($achievementPct < 80 || $achievementPct > 100)) return false;
            if ($filterPct === 'over100' && $achievementPct <= 100) return false;
        }
        return true;
    })->map(function ($g) use ($now, $goalSummaryByKey) {
        $expired = false;
        $sortStart = '0000-00-00';
        $sortEnd = '9999-12-31';
        if ($g->period_type === 'month' && $g->year && $g->month) {
            $start = \Carbon\Carbon::create($g->year, $g->month, 1);
            $sortStart = $start->format('Y-m-d');
            $sortEnd = $start->copy()->endOfMonth()->format('Y-m-d');
            $expired = $g->year < $now->year || ($g->year == $now->year && $g->month < $now->month);
        } elseif ($g->period_type === 'custom' && $g->period_start && $g->period_end) {
            $start = \Carbon\Carbon::parse($g->period_start);
            $end = \Carbon\Carbon::parse($g->period_end)->endOfDay();
            $sortStart = $start->format('Y-m-d');
            $sortEnd = $end->format('Y-m-d');
            $expired = $end->lt($now);
        }
        $row = $goalSummaryByKey->get($g->id) ?? $goalSummaryByKey->get($g->name);
        $met = $row['met'] ?? false;
        $target = $g->amount_target_vnd ?? 0;
        $sortKey = (int) \Carbon\Carbon::parse($sortEnd)->format('Ymd');
        return (object)['type' => 'goal', 'item' => $g, '_expired' => $expired, '_start' => $sortStart, '_end' => $sortEnd, '_sort_key' => $sortKey, '_flag' => $met ? 1 : 0, '_limit' => $target];
    })->values();

    $mergedItems = $filteredThresholds->concat($filteredGoals)->sortBy([
        fn($x) => (int) ($x->_sort_key ?? \Carbon\Carbon::parse($x->_end)->format('Ymd')),
        fn($x) => $x->_flag ? 0 : 1,
        fn($x) => $x->_limit,
        fn($x) => $x->type === 'threshold' ? $x->item->id : 1000000 + $x->item->id,
    ])->values();

    $countText = $filteredThresholds->count() . ' ngưỡng · ' . $filteredGoals->count() . ' mục tiêu';
@endphp
<div id="nguong-ngan-sach-list-wrap" data-ajax-container data-count="{{ $mergedItems->count() }}" data-count-text="{{ $countText }}">
    @if($thresholds->isEmpty() && $incomeGoals->isEmpty())
        <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-6 dark:border-gray-700 dark:bg-gray-800/50">
            <p class="text-gray-600 dark:text-gray-400">Nhấn <strong>Thêm ngưỡng</strong> hoặc <strong>Thêm mục tiêu</strong> để bắt đầu.</p>
        </div>
    @elseif($mergedItems->isEmpty())
        <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-6 dark:border-gray-700 dark:bg-gray-800/50">
            <p class="text-gray-600 dark:text-gray-400">Không có mục nào thỏa bộ lọc. Thử <button type="button" class="font-medium text-brand-600 dark:text-brand-400 underline" id="nguong-xoa-loc-inline">xóa lọc</button>.</p>
        </div>
    @else
        <ul class="space-y-4">
            @foreach($mergedItems as $entry)
                @if($entry->type === 'threshold')
                @php $t = $entry->item; @endphp
                @php
                    $row = $summaryByKey->get($t->id) ?? $summaryByKey->get($t->name);
                    $spent = $row['spent_vnd'] ?? 0;
                    $limit = $t->amount_limit_vnd;
                    $deviation = $row['deviation_pct'] ?? null;
                    $breached = $row['breached'] ?? false;
                    $streak = $row['breach_streak'] ?? 0;
                    $periodLabel = $t->period_type === 'month' && $t->year && $t->month ? 'T' . $t->month . '/' . $t->year : ($t->period_type === 'custom' && $t->period_start && $t->period_end ? \Carbon\Carbon::parse($t->period_start)->format('d/m') . ' – ' . \Carbon\Carbon::parse($t->period_end)->format('d/m/Y') : 'Tháng hiện tại');
                    $pct = $limit > 0 ? round(($spent / $limit) * 100, 1) : 0;
                    $barPct = min(100, $pct);
                    $barColor = $breached ? 'bg-black dark:bg-white' : ($pct >= 90 ? 'bg-red-500' : ($pct >= 60 ? 'bg-amber-500' : 'bg-emerald-500'));
                    $expired = false;
                    if ($t->period_type === 'month' && $t->year && $t->month) {
                        $expired = $t->year < $now->year || ($t->year == $now->year && $t->month < $now->month);
                    } elseif ($t->period_type === 'custom' && $t->period_end) {
                        $expired = \Carbon\Carbon::parse($t->period_end)->endOfDay()->lt($now);
                    }
                    $expiredTag = null;
                    $expiredTagClass = '';
                    $expiredTagFold = '';
                    if ($expired) {
                        if (!$breached) {
                            $expiredTag = $pct < 60 ? 'Rất tốt' : 'Hoàn thành';
                            $expiredTagClass = $pct < 60 ? 'bg-gradient-to-b from-emerald-500 to-emerald-700' : 'bg-gradient-to-b from-blue-500 to-blue-700';
                            $expiredTagFold = $pct < 60 ? 'bg-emerald-800' : 'bg-blue-800';
                        } else {
                            $expiredTag = $pct <= 130 ? 'Cần điều chỉnh' : 'Chi tiêu cao';
                            $expiredTagClass = $pct <= 130 ? 'bg-gradient-to-b from-amber-400 to-amber-600' : 'bg-gradient-to-b from-red-500 to-red-700';
                            $expiredTagFold = $pct <= 130 ? 'bg-amber-700' : 'bg-red-800';
                        }
                    } elseif ($breached) {
                        $expiredTag = $pct <= 130 ? 'Cần điều chỉnh' : 'Chi tiêu cao';
                        $expiredTagClass = $pct <= 130 ? 'bg-gradient-to-b from-amber-400 to-amber-600' : 'bg-gradient-to-b from-red-500 to-red-700';
                        $expiredTagFold = $pct <= 130 ? 'bg-amber-700' : 'bg-red-800';
                    }
                @endphp
                <li class="group relative overflow-visible rounded-xl border border-gray-200 border-l-[6px] border-l-orange-500 bg-white p-3 dark:border-gray-700 dark:bg-gray-800/50 dark:text-white">
                    @if(!empty($expiredTag))
                        <span class="absolute left-0 top-0 z-10 flex h-7 min-w-[4.5rem] overflow-hidden rounded-tl-md text-white drop-shadow-md">
                            <span class="relative flex items-center rounded-r-md px-2.5 py-1 pl-3 text-xs font-semibold {{ $expiredTagClass }}" style="clip-path: polygon(14px 100%, 0 100%, 0 0, 100% 0, 100% 100%); box-shadow: 0 2px 8px rgba(0,0,0,0.22), inset 0 1px 0 rgba(255,255,255,0.25);">
                                <span class="absolute left-0 bottom-0 h-3 w-3 {{ $expiredTagFold }}" style="clip-path: polygon(0 100%, 100% 100%, 0 0);"></span>
                                <span class="relative z-10">{{ $expiredTag }}</span>
                            </span>
                        </span>
                    @endif
                    <div class="flex items-center gap-2" @if(!empty($expiredTag)) style="padding-left: 8rem;" @endif>
                        <p class="min-w-0 flex-1 text-theme-sm text-gray-700 dark:text-gray-300 truncate">
                            <span class="font-medium text-gray-900 dark:text-white">{{ $t->name }}</span>
                            · Kỳ {{ $periodLabel }} · Ngưỡng {{ number_format($limit) }} ₫
                            @if($row)
                                · Đã chi <span class="{{ $breached ? 'text-gray-900 dark:text-white font-medium' : '' }}">{{ number_format($spent) }} ₫</span>
                                @if($deviation !== null)<span class="text-theme-xs">({{ $deviation > 0 ? '+' : '' }}{{ $deviation }}%)</span>@endif
                                @if($breached)
                                    <span class="text-theme-xs font-medium text-gray-700 dark:text-gray-300"> · Vượt ngưỡng</span>@if($streak > 0)<span class="text-theme-xs text-gray-500 dark:text-gray-400"> · {{ $streak }} kỳ</span>@endif
                                @endif
                            @endif
                        </p>
                        <div class="flex shrink-0 items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
                            <a href="{{ route('tai-chinh', ['tab' => 'nguong-ngan-sach', 'edit' => $t->id]) }}" class="shrink-0 rounded-full p-1.5 text-gray-400 hover:bg-gray-200 hover:text-gray-700 dark:hover:bg-gray-600 dark:hover:text-gray-200" title="Sửa"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg></a>
                            <form id="form-delete-nguong-{{ $t->id }}" action="{{ route('tai-chinh.nguong-ngan-sach.destroy', $t->id) }}" method="post" class="inline">@csrf @method('DELETE')<button type="button" @click="$dispatch('confirm-delete-open', { formId: 'form-delete-nguong-{{ $t->id }}' })" class="shrink-0 rounded-full p-1.5 text-gray-400 hover:bg-gray-200 hover:text-gray-700 dark:hover:bg-gray-600 dark:hover:text-gray-200" title="Xóa"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg></button></form>
                        </div>
                    </div>
                    <div class="relative mt-2 h-2 w-full overflow-visible rounded-full bg-gray-200 dark:bg-gray-700">
                        <div class="h-full overflow-hidden rounded-full relative"><div class="h-full rounded-full {{ $barColor }}" style="width: {{ $barPct }}%;"></div>@if(!$expired)<span class="nguong-progress-shimmer" aria-hidden="true"></span>@endif</div>
                        @if(!$expired && $barPct > 0)<span class="absolute top-1/2 z-20 h-2.5 w-2.5 -translate-y-1/2 -translate-x-1/2 rounded-full {{ $barColor }} shadow-sm" style="left: {{ $barPct }}%;"><span class="absolute inline-flex h-full w-full rounded-full {{ $barColor }} -z-10 nguong-progress-ping-strong opacity-90"></span></span>@endif
                    </div>
                    @if($breached && $streak >= 2)<p class="mt-1.5 text-theme-xs text-amber-700 dark:text-amber-300">Bạn đã vượt ngưỡng này {{ $streak }} kỳ liên tiếp — nên xem lại thói quen chi.</p>@endif
                </li>
                @else
                @php
                    $g = $entry->item;
                    $row = $goalSummaryByKey->get($g->id) ?? $goalSummaryByKey->get($g->name);
                    $earned = $row['earned_vnd'] ?? 0;
                    $target = $g->amount_target_vnd;
                    $achievementPct = $row['achievement_pct'] ?? ($target > 0 ? round(($earned / $target) * 100, 1) : 0);
                    $met = $row['met'] ?? ($earned >= $target);
                    $streak = $row['achievement_streak'] ?? 0;
                    $periodLabel = $g->period_type === 'month' && $g->year && $g->month ? 'T' . $g->month . '/' . $g->year : ($g->period_type === 'custom' && $g->period_start && $g->period_end ? \Carbon\Carbon::parse($g->period_start)->format('d/m') . ' – ' . \Carbon\Carbon::parse($g->period_end)->format('d/m/Y') : 'Tháng hiện tại');
                    $barPct = min(100, $achievementPct);
                    $barColor = ($met || $achievementPct >= 80) ? 'bg-emerald-500' : ($achievementPct >= 50 ? 'bg-amber-500' : 'bg-gray-400');
                    $expired = false;
                    if ($g->period_type === 'month' && $g->year && $g->month) { $expired = $g->year < $now->year || ($g->year == $now->year && $g->month < $now->month); } elseif ($g->period_type === 'custom' && $g->period_end) { $expired = \Carbon\Carbon::parse($g->period_end)->endOfDay()->lt($now); }
                    $expiredTag = null; $expiredTagClass = ''; $expiredTagFold = '';
                    if ($expired) {
                        if ($met) { $expiredTag = $achievementPct >= 100 ? 'Hoàn thành' : 'Đạt mục tiêu'; $expiredTagClass = 'bg-gradient-to-b from-emerald-500 to-emerald-700'; $expiredTagFold = 'bg-emerald-800'; }
                        else { $expiredTag = $achievementPct >= 80 ? 'Gần đạt' : 'Chưa đạt'; $expiredTagClass = $achievementPct >= 80 ? 'bg-gradient-to-b from-amber-400 to-amber-600' : 'bg-gradient-to-b from-gray-500 to-gray-700'; $expiredTagFold = $achievementPct >= 80 ? 'bg-amber-700' : 'bg-gray-800'; }
                    } elseif (!$met && $achievementPct >= 80) { $expiredTag = 'Gần đạt'; $expiredTagClass = 'bg-gradient-to-b from-amber-400 to-amber-600'; $expiredTagFold = 'bg-amber-700'; }
                @endphp
                <li class="group relative overflow-visible rounded-xl border border-gray-200 border-l-[6px] border-l-emerald-700 dark:border-l-emerald-600 bg-white p-3 dark:border-gray-700 dark:bg-gray-800/50 dark:text-white">
                    @if($expiredTag)
                        <span class="absolute left-0 top-0 z-10 flex h-7 min-w-[4.5rem] overflow-hidden rounded-tl-md text-white drop-shadow-md">
                            <span class="relative flex items-center rounded-r-md px-2.5 py-1 pl-3 text-xs font-semibold {{ $expiredTagClass }}" style="clip-path: polygon(14px 100%, 0 100%, 0 0, 100% 0, 100% 100%); box-shadow: 0 2px 8px rgba(0,0,0,0.22), inset 0 1px 0 rgba(255,255,255,0.25);">
                                <span class="absolute left-0 bottom-0 h-3 w-3 {{ $expiredTagFold }}" style="clip-path: polygon(0 100%, 100% 100%, 0 0);"></span>
                                <span class="relative z-10">{{ $expiredTag }}</span>
                            </span>
                        </span>
                    @endif
                    <div class="flex items-center gap-2" @if($expiredTag) style="padding-left: 8rem;" @endif>
                        <p class="min-w-0 flex-1 text-theme-sm text-gray-700 dark:text-gray-300 truncate">
                            <span class="font-medium text-gray-900 dark:text-white">{{ $g->name }}</span>
                            · Kỳ {{ $periodLabel }} · Mục tiêu {{ number_format($target) }} ₫ · {{ $met ? 'Đã đạt' : 'Tiến trình ' . (!empty($g->expense_category_bindings) ? '(tổng thu − tổng chi)' : '(tổng thu)') }}: <span class="{{ $met ? 'text-emerald-600 dark:text-emerald-400 font-medium' : '' }}">{{ number_format($earned) }} ₫</span>
                            <span class="text-theme-xs">({{ $achievementPct }}%)</span>
                            @if($met)
                                <span class="text-theme-xs font-medium text-emerald-600 dark:text-emerald-400"> · Đạt</span>@if($streak > 0)<span class="text-theme-xs text-gray-500 dark:text-gray-400"> · {{ $streak }} kỳ</span>@endif
                            @else
                                <span class="text-theme-xs text-gray-500 dark:text-gray-400"> · Chưa đạt</span>@if($achievementPct >= 80 && $achievementPct < 100)<span class="text-theme-xs text-amber-700 dark:text-amber-300"> · Còn {{ number_format(100 - $achievementPct, 1) }}% nữa đạt mục tiêu.</span>@endif
                            @endif
                        </p>
                        <div class="flex shrink-0 items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
                            <a href="{{ route('tai-chinh', ['tab' => 'nguong-ngan-sach', 'edit_goal' => $g->id]) }}" class="shrink-0 rounded-full p-1.5 text-gray-400 hover:bg-gray-200 hover:text-gray-700 dark:hover:bg-gray-600 dark:hover:text-gray-200" title="Sửa"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg></a>
                            <form id="form-delete-muc-tieu-{{ $g->id }}" action="{{ route('tai-chinh.muc-tieu-thu.destroy', $g->id) }}" method="post" class="inline">@csrf @method('DELETE')<button type="button" @click="$dispatch('confirm-delete-open', { formId: 'form-delete-muc-tieu-{{ $g->id }}' })" class="shrink-0 rounded-full p-1.5 text-gray-400 hover:bg-gray-200 hover:text-gray-700 dark:hover:bg-gray-600 dark:hover:text-gray-200" title="Xóa"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg></button></form>
                        </div>
                    </div>
                    <div class="relative mt-2 h-2 w-full overflow-visible rounded-full bg-gray-200 dark:bg-gray-700">
                        <div class="h-full overflow-hidden rounded-full relative"><div class="h-full rounded-full {{ $barColor }}" style="width: {{ $barPct }}%;"></div>@if(!$expired)<span class="nguong-progress-shimmer" aria-hidden="true"></span>@endif</div>
                        @if(!$expired && $barPct > 0)<span class="absolute top-1/2 z-20 h-2.5 w-2.5 -translate-y-1/2 -translate-x-1/2 rounded-full {{ $barColor }} shadow-sm" style="left: {{ $barPct }}%;"><span class="absolute inline-flex h-full w-full rounded-full {{ $barColor }} -z-10 nguong-progress-ping-strong opacity-90"></span></span>@endif
                    </div>
                </li>
                @endif
            @endforeach
        </ul>
    @endif
</div>
