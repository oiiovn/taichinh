@php
    $thresholds = $budgetThresholds ?? collect();
    $summary = $budgetThresholdSummary ?? ['thresholds' => [], 'aggregate' => []];
    $summaryItems = $summary['thresholds'] ?? [];
    $summaryByKey = collect($summaryItems)->keyBy('name');
    $userCategories = $userCategories ?? collect();
    $expenseUserCats = $userCategories->whereIn('type', ['expense'])->values();
    $incomeUserCats = $userCategories->whereIn('type', ['income'])->values();
    $editThreshold = $editThreshold ?? null;
    $openModalNguong = $openModalNguong ?? false;
    $modalOpen = $openModalNguong || ($editThreshold && $editThreshold->id);
    $bindIds = $editThreshold ? array_column($editThreshold->category_bindings ?? [], 'id') : [];
    $incomeGoals = $incomeGoals ?? collect();
    $incomeGoalSummary = $incomeGoalSummary ?? ['goals' => []];
    $goalSummaryByKey = collect($incomeGoalSummary['goals'] ?? [])->keyBy('name');
    $editIncomeGoal = $editIncomeGoal ?? null;
    $openModalMucTieuThu = $openModalMucTieuThu ?? false;
    $goalBindIds = $editIncomeGoal ? array_column($editIncomeGoal->category_bindings ?? [], 'id') : [];
    $goalExpenseBindIds = $editIncomeGoal ? array_column($editIncomeGoal->expense_category_bindings ?? [], 'id') : [];
    $now = now();

    $filterPct = request('filter_pct', '');
    $filterVuot = request('filter_vuot', '');
    $filterHetHan = request('filter_het_han', '');
    $filteredThresholds = $thresholds->filter(function ($t) use ($summaryByKey, $now, $filterPct, $filterVuot, $filterHetHan) {
        $row = $summaryByKey->get($t->name);
        $spent = $row['spent_vnd'] ?? 0;
        $limit = $t->amount_limit_vnd;
        $breached = $row['breached'] ?? false;
        $pct = $limit > 0 ? round(($spent / $limit) * 100, 1) : 0;
        $expired = false;
        $periodEndInPastMonth = false;
        if ($t->period_type === 'month' && $t->year && $t->month) {
            $expired = $t->year < $now->year || ($t->year == $now->year && $t->month < $now->month);
            $periodEndInPastMonth = $t->year < $now->year || ($t->year == $now->year && $t->month < $now->month);
        } elseif ($t->period_type === 'custom' && $t->period_end) {
            $periodEnd = \Carbon\Carbon::parse($t->period_end)->endOfDay();
            $expired = $periodEnd->lt($now);
            $periodEndInPastMonth = $periodEnd->lt($now->copy()->startOfMonth());
        }
        if ($filterVuot === '1' && !$breached) return false;
        if ($filterHetHan === '1') {
            if (!$periodEndInPastMonth) return false;
        } else {
            if ($periodEndInPastMonth) return false;
        }
        if ($filterPct !== '') {
            if ($filterPct === 'under60' && $pct >= 60) return false;
            if ($filterPct === '60_80' && ($pct < 60 || $pct >= 80)) return false;
            if ($filterPct === '80_100' && ($pct < 80 || $pct > 100)) return false;
            if ($filterPct === 'over100' && $pct <= 100) return false;
        }
        return true;
    });
    $filteredThresholds = $filteredThresholds->map(function ($t) use ($now, $summaryByKey) {
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
        $row = $summaryByKey->get($t->name);
        $breached = $row['breached'] ?? false;
        $limit = $t->amount_limit_vnd ?? 0;
        return (object)['type' => 'threshold', 'item' => $t, '_expired' => $expired, '_start' => $sortStart, '_end' => $sortEnd, '_flag' => $breached ? 1 : 0, '_limit' => $limit];
    })->values();

    $filteredGoals = $incomeGoals->filter(function ($g) use ($goalSummaryByKey, $now, $filterPct, $filterVuot, $filterHetHan) {
        $row = $goalSummaryByKey->get($g->name);
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
        $row = $goalSummaryByKey->get($g->name);
        $met = $row['met'] ?? false;
        $target = $g->amount_target_vnd ?? 0;
        return (object)['type' => 'goal', 'item' => $g, '_expired' => $expired, '_start' => $sortStart, '_end' => $sortEnd, '_flag' => $met ? 1 : 0, '_limit' => $target];
    })->values();

    $mergedItems = $filteredThresholds->concat($filteredGoals)->sortBy([
        fn($x) => $x->_expired ? 1 : 0,
        fn($x) => -\Carbon\Carbon::parse($x->_start)->timestamp,
        fn($x) => $x->_end,
        fn($x) => $x->_flag ? 0 : 1,
        fn($x) => $x->_limit,
    ])->values();

    $hasFilterNguong = $filterPct !== '' || $filterVuot === '1' || $filterHetHan === '1';
    $pctLabels = ['' => 'Tất cả %', 'under60' => 'Dưới 60%', '60_80' => '60-80%', '80_100' => '80-100%', 'over100' => 'Trên 100%'];
@endphp
<style>
@keyframes nguong-ping-strong {
    0% { transform: scale(1); opacity: 0.9; }
    100% { transform: scale(3); opacity: 0; }
}
.nguong-progress-ping-strong {
    animation: nguong-ping-strong 1.2s cubic-bezier(0, 0, 0.2, 1) infinite;
}
@keyframes nguong-shimmer-run {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
.nguong-progress-shimmer {
    position: absolute;
    inset: 0;
    border-radius: inherit;
    overflow: hidden;
    pointer-events: none;
}
.nguong-progress-shimmer::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 40%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.35), transparent);
    animation: nguong-shimmer-run 2s ease-in-out infinite;
}
</style>
<div id="nguong-ngan-sach-wrap" class="space-y-6" x-data="{ modalThemNguong: false, modalThemMucTieu: false, showConfirmDelete: false, formIdToSubmit: null }" @nguong-saved.window="modalThemNguong = false" @muc-tieu-saved.window="modalThemMucTieu = false" @open-nguong-modal.window="modalThemNguong = true" @open-muc-tieu-modal.window="modalThemMucTieu = true" @confirm-delete-open.window="showConfirmDelete = true; formIdToSubmit = $event.detail.formId" @confirm-delete.window="if (formIdToSubmit) { const f = document.getElementById(formIdToSubmit); if (f) f.submit(); } formIdToSubmit = null; showConfirmDelete = false"
    data-nguong-store-url="{{ route('tai-chinh.nguong-ngan-sach.store') }}" data-nguong-update-url="{{ route('tai-chinh.nguong-ngan-sach.update', ['id' => 0]) }}"
    data-muc-tieu-store-url="{{ route('tai-chinh.muc-tieu-thu.store') }}" data-muc-tieu-update-url="{{ route('tai-chinh.muc-tieu-thu.update', ['id' => 0]) }}">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Ngưỡng ngân sách</h2>

    {{-- Bộ lọc (giống lịch sử giao dịch) --}}
    <form id="form-nguong-filter" method="GET" action="{{ route('tai-chinh') }}">
        <input type="hidden" name="tab" value="nguong-ngan-sach">
        <input type="hidden" name="filter_pct" id="filter_pct" value="{{ $filterPct }}">
        <input type="hidden" name="filter_vuot" id="filter_vuot" value="{{ $filterVuot }}">
        <input type="hidden" name="filter_het_han" id="filter_het_han" value="{{ $filterHetHan }}">
        @if(request('edit'))<input type="hidden" name="edit" value="{{ request('edit') }}">@endif
        @if(request('openModal'))<input type="hidden" name="openModal" value="1">@endif
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <button type="button" id="btn-nguong-pct" class="inline-flex h-[42px] items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-theme-sm font-medium shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-white/[0.03] {{ $filterPct !== '' ? 'text-gray-700 dark:text-gray-200' : 'text-gray-400 dark:text-gray-500' }}" data-cycle="{{ implode(',', array_keys($pctLabels)) }}">{{ $pctLabels[$filterPct] ?? 'Tất cả %' }}</button>
            <button type="button" id="btn-nguong-vuot" class="inline-flex h-[42px] items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-theme-sm font-medium shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-white/[0.03] {{ $filterVuot === '1' ? 'font-semibold text-gray-700 dark:text-gray-200' : 'text-gray-400 dark:text-gray-500' }}">Vượt ngưỡng</button>
            <button type="button" id="btn-nguong-hethan" class="inline-flex h-[42px] items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-theme-sm font-medium shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-white/[0.03] {{ $filterHetHan === '1' ? 'font-semibold text-gray-700 dark:text-gray-200' : 'text-gray-400 dark:text-gray-500' }}">Hết hạn</button>
            <button type="button" id="btn-nguong-xoa-loc" class="inline-flex h-[42px] items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-3 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-white/[0.03]" style="{{ $hasFilterNguong ? '' : 'display:none;' }}">Xóa lọc</button>
            <div class="ml-auto flex items-center gap-2">
                <span id="nguong-count" class="text-theme-sm text-gray-600 dark:text-gray-400">{{ $filteredThresholds->count() }} ngưỡng · {{ $filteredGoals->count() }} mục tiêu</span>
                <button type="button" @click="$dispatch('open-nguong-modal-add')"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-theme-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-brand-100 text-brand-600 dark:bg-brand-500/20 dark:text-brand-400" aria-hidden="true">+</span>
                    Thêm ngưỡng
                </button>
                <button type="button" @click="$dispatch('open-muc-tieu-modal-add')"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-theme-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400" aria-hidden="true">+</span>
                    Thêm mục tiêu
                </button>
            </div>
        </div>
    </form>

    <div id="nguong-ajax-message" class="mb-2 hidden rounded-lg border p-3 text-sm" role="alert" aria-live="polite"></div>
    <div id="nguong-ngan-sach-container" data-table-url="{{ route('tai-chinh.nguong-ngan-sach-table') }}">
    @if($thresholds->isEmpty() && $incomeGoals->isEmpty())
        <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-6 dark:border-gray-700 dark:bg-gray-800/50">
            <p class="text-gray-600 dark:text-gray-400">Nhấn <strong>Thêm ngưỡng</strong> hoặc <strong>Thêm mục tiêu</strong> để bắt đầu.</p>
        </div>
    @else
        @if($mergedItems->isEmpty())
            <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-6 dark:border-gray-700 dark:bg-gray-800/50">
                <p class="text-gray-600 dark:text-gray-400">Không có mục nào thỏa bộ lọc. Thử <a href="{{ route('tai-chinh', ['tab' => 'nguong-ngan-sach']) }}" class="font-medium text-brand-600 dark:text-brand-400 underline">xóa lọc</a>.</p>
            </div>
        @else
        <ul class="space-y-4">
            @foreach($mergedItems as $entry)
                @if($entry->type === 'threshold')
                @php $t = $entry->item; @endphp
                @php
                    $row = $summaryByKey->get($t->name);
                    $spent = $row['spent_vnd'] ?? 0;
                    $limit = $t->amount_limit_vnd;
                    $deviation = $row['deviation_pct'] ?? null;
                    $breached = $row['breached'] ?? false;
                    $streak = $row['breach_streak'] ?? 0;
                    $periodLabel = $t->period_type === 'month' && $t->year && $t->month
                        ? 'T' . $t->month . '/' . $t->year
                        : ($t->period_type === 'custom' && $t->period_start && $t->period_end
                            ? \Carbon\Carbon::parse($t->period_start)->format('d/m') . ' – ' . \Carbon\Carbon::parse($t->period_end)->format('d/m/Y')
                            : 'Tháng hiện tại');
                @endphp
                @php
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
                            <span class="font-medium text-gray-900 dark:text-white">{{ $t->name }}</span>
                            · Kỳ {{ $periodLabel }} · Ngưỡng {{ number_format($limit) }} ₫
                            @if($row)
                                · Đã chi <span class="{{ $breached ? 'text-gray-900 dark:text-white font-medium' : '' }}">{{ number_format($spent) }} ₫</span>
                                @if($deviation !== null)
                                    <span class="text-theme-xs">({{ $deviation > 0 ? '+' : '' }}{{ $deviation }}%)</span>
                                @endif
                                @if($breached)
                                    <span class="text-theme-xs font-medium text-gray-700 dark:text-gray-300"> · Vượt ngưỡng</span>
                                    @if($streak > 0)
                                        <span class="text-theme-xs text-gray-500 dark:text-gray-400"> · {{ $streak }} kỳ</span>
                                    @endif
                                @endif
                            @endif
                        </p>
                        <div class="flex shrink-0 items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
                            <a href="{{ route('tai-chinh', ['tab' => 'nguong-ngan-sach', 'edit' => $t->id]) }}" class="shrink-0 rounded-full p-1.5 text-gray-400 hover:bg-gray-200 hover:text-gray-700 dark:hover:bg-gray-600 dark:hover:text-gray-200" title="Sửa">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                            </a>
                            <form id="form-delete-nguong-{{ $t->id }}" action="{{ route('tai-chinh.nguong-ngan-sach.destroy', $t->id) }}" method="post" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="button" @click="$dispatch('confirm-delete-open', { formId: 'form-delete-nguong-{{ $t->id }}' })" class="shrink-0 rounded-full p-1.5 text-gray-400 hover:bg-gray-200 hover:text-gray-700 dark:hover:bg-gray-600 dark:hover:text-gray-200" title="Xóa">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="relative mt-2 h-2 w-full overflow-visible rounded-full bg-gray-200 dark:bg-gray-700">
                        <div class="h-full overflow-hidden rounded-full relative">
                            <div class="h-full rounded-full {{ $barColor }}" style="width: {{ $barPct }}%;"></div>
                            @if(!$expired)
                            <span class="nguong-progress-shimmer" aria-hidden="true"></span>
                            @endif
                        </div>
                        @if(!$expired && $barPct > 0)
                            <span class="absolute top-1/2 z-20 h-2.5 w-2.5 -translate-y-1/2 -translate-x-1/2 rounded-full {{ $barColor }} shadow-sm" style="left: {{ $barPct }}%;" aria-hidden="true">
                                <span class="absolute inline-flex h-full w-full rounded-full {{ $barColor }} -z-10 nguong-progress-ping-strong opacity-90"></span>
                            </span>
                        @endif
                    </div>
                    @if($breached && $streak >= 2)
                        <p class="mt-1.5 text-theme-xs text-amber-700 dark:text-amber-300">Bạn đã vượt ngưỡng này {{ $streak }} kỳ liên tiếp — nên xem lại thói quen chi.</p>
                    @endif
                </li>
                @else
                @php
                    $g = $entry->item;
                    $row = $goalSummaryByKey->get($g->name);
                    $earned = $row['earned_vnd'] ?? 0;
                    $target = $g->amount_target_vnd;
                    $achievementPct = $row['achievement_pct'] ?? ($target > 0 ? round(($earned / $target) * 100, 1) : 0);
                    $met = $row['met'] ?? ($earned >= $target);
                    $streak = $row['achievement_streak'] ?? 0;
                    $periodLabel = $g->period_type === 'month' && $g->year && $g->month ? 'T' . $g->month . '/' . $g->year : ($g->period_type === 'custom' && $g->period_start && $g->period_end ? \Carbon\Carbon::parse($g->period_start)->format('d/m') . ' – ' . \Carbon\Carbon::parse($g->period_end)->format('d/m/Y') : 'Tháng hiện tại');
                    $barPct = min(100, $achievementPct);
                    $barColor = ($met || $achievementPct >= 80) ? 'bg-emerald-500' : ($achievementPct >= 50 ? 'bg-amber-500' : 'bg-gray-400');
                    $expired = false;
                    if ($g->period_type === 'month' && $g->year && $g->month) {
                        $expired = $g->year < $now->year || ($g->year == $now->year && $g->month < $now->month);
                    } elseif ($g->period_type === 'custom' && $g->period_end) {
                        $expired = \Carbon\Carbon::parse($g->period_end)->endOfDay()->lt($now);
                    }
                    $expiredTag = null;
                    $expiredTagClass = '';
                    $expiredTagFold = '';
                    if ($expired) {
                        if ($met) {
                            $expiredTag = $achievementPct >= 100 ? 'Hoàn thành' : 'Đạt mục tiêu';
                            $expiredTagClass = 'bg-gradient-to-b from-emerald-500 to-emerald-700';
                            $expiredTagFold = 'bg-emerald-800';
                        } else {
                            $expiredTag = $achievementPct >= 80 ? 'Gần đạt' : 'Chưa đạt';
                            $expiredTagClass = $achievementPct >= 80 ? 'bg-gradient-to-b from-amber-400 to-amber-600' : 'bg-gradient-to-b from-gray-500 to-gray-700';
                            $expiredTagFold = $achievementPct >= 80 ? 'bg-amber-700' : 'bg-gray-800';
                        }
                    } elseif (!$met && $achievementPct >= 80) {
                        $expiredTag = 'Gần đạt';
                        $expiredTagClass = 'bg-gradient-to-b from-amber-400 to-amber-600';
                        $expiredTagFold = 'bg-amber-700';
                    }
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
                                <span class="text-theme-xs font-medium text-emerald-600 dark:text-emerald-400"> · Đạt</span>
                                @if($streak > 0) <span class="text-theme-xs text-gray-500 dark:text-gray-400"> · {{ $streak }} kỳ</span> @endif
                            @else
                                <span class="text-theme-xs text-gray-500 dark:text-gray-400"> · Chưa đạt</span>@if($achievementPct >= 80 && $achievementPct < 100)<span class="text-theme-xs text-amber-700 dark:text-amber-300"> · Còn {{ number_format(100 - $achievementPct, 1) }}% nữa đạt mục tiêu.</span>@endif
                            @endif
                        </p>
                        <div class="flex shrink-0 items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
                            <a href="{{ route('tai-chinh', ['tab' => 'nguong-ngan-sach', 'edit_goal' => $g->id]) }}" class="shrink-0 rounded-full p-1.5 text-gray-400 hover:bg-gray-200 hover:text-gray-700 dark:hover:bg-gray-600 dark:hover:text-gray-200" title="Sửa"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg></a>
                            <form id="form-delete-muc-tieu-{{ $g->id }}" action="{{ route('tai-chinh.muc-tieu-thu.destroy', $g->id) }}" method="post" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="button" @click="$dispatch('confirm-delete-open', { formId: 'form-delete-muc-tieu-{{ $g->id }}' })" class="shrink-0 rounded-full p-1.5 text-gray-400 hover:bg-gray-200 hover:text-gray-700 dark:hover:bg-gray-600 dark:hover:text-gray-200" title="Xóa"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg></button>
                            </form>
                        </div>
                    </div>
                    <div class="relative mt-2 h-2 w-full overflow-visible rounded-full bg-gray-200 dark:bg-gray-700">
                        <div class="h-full overflow-hidden rounded-full relative">
                            <div class="h-full rounded-full {{ $barColor }}" style="width: {{ $barPct }}%;"></div>
                            @if(!$expired)
                            <span class="nguong-progress-shimmer" aria-hidden="true"></span>
                            @endif
                        </div>
                        @if(!$expired && $barPct > 0)
                            <span class="absolute top-1/2 z-20 h-2.5 w-2.5 -translate-y-1/2 -translate-x-1/2 rounded-full {{ $barColor }} shadow-sm" style="left: {{ $barPct }}%;" aria-hidden="true">
                                <span class="absolute inline-flex h-full w-full rounded-full {{ $barColor }} -z-10 nguong-progress-ping-strong opacity-90"></span>
                            </span>
                        @endif
                    </div>
                </li>
                @endif
            @endforeach
        </ul>
        @endif
    @endif
    </div>

    {{-- Modal Thêm ngưỡng --}}
    <div x-show="modalThemNguong" x-cloak class="fixed inset-0 z-[100000] flex items-center justify-center overflow-y-auto p-4"
        @keydown.escape.window="modalThemNguong = false">
        <div x-show="modalThemNguong" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-gray-500/60 dark:bg-gray-900/70" @click="modalThemNguong = false"></div>
        <div x-show="modalThemNguong" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
            class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-800 dark:text-white">
            <div class="flex items-center justify-between mb-4">
                <h3 id="modal-nguong-title" class="text-lg font-semibold text-gray-900 dark:text-white">Thêm ngưỡng</h3>
                <button type="button" @click="modalThemNguong = false" class="rounded-full p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form id="form-nguong-modal" action="{{ route('tai-chinh.nguong-ngan-sach.store') }}" method="post" class="space-y-4">
                @csrf
                <div>
                    <label for="nguong_name" class="block text-theme-sm font-medium text-gray-700 dark:text-gray-300">Tên ngưỡng</label>
                    <input type="text" name="name" id="nguong_name" value="{{ old('name', '') }}" required maxlength="255"
                        class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        placeholder="VD: Ăn uống tháng này">
                    @error('name')
                        <p class="mt-1 text-theme-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="rounded-lg border-2 border-dashed border-gray-300 bg-gray-50/50 p-4 dark:border-gray-600 dark:bg-gray-800/50">
                    <div class="min-w-[200px] max-w-full">
                        <p class="mb-2 text-theme-xs font-medium text-gray-600 dark:text-gray-400">Danh mục chi của bạn</p>
                        <div class="min-h-[60px] space-y-1.5 max-h-40 overflow-y-auto rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-600 dark:bg-gray-700/50">
                            @forelse($expenseUserCats as $uc)
                                <label class="flex items-center gap-2 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600/50 rounded px-2 py-1 -mx-2 -my-0.5">
                                    <input type="checkbox" data-type="user_category" data-id="{{ $uc->id }}" class="cat-binding rounded border-gray-400 text-brand-600 focus:ring-brand-500" {{ in_array($uc->id, $bindIds ?? []) ? 'checked' : '' }}>
                                    <span class="text-theme-sm text-gray-800 dark:text-gray-200">{{ $uc->name }}</span>
                                </label>
                            @empty
                                <p class="text-theme-xs text-gray-500 dark:text-gray-400 italic">Chưa có danh mục chi. Vào tab Giao dịch / Phân tích trước.</p>
                            @endforelse
                        </div>
                    </div>
                    <p class="mt-2 text-theme-xs text-gray-500 dark:text-gray-400">Đã chọn: <span id="cat-binding-count">0</span> mục</p>
                    <input type="hidden" name="category_bindings" id="category_bindings_json" value="">
                    @error('category_bindings')
                        <p class="mt-1 text-theme-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-wrap gap-4">
                    <div>
                        <label for="period_type" class="block text-theme-sm font-medium text-gray-700 dark:text-gray-300">Kỳ</label>
                        <select name="period_type" id="period_type" class="mt-1 rounded-lg border border-gray-300 px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="month" {{ old('period_type', 'month') === 'month' ? 'selected' : '' }}>Theo tháng</option>
                            <option value="custom" {{ old('period_type', '') === 'custom' ? 'selected' : '' }}>Tùy chọn (từ – đến)</option>
                        </select>
                    </div>
                    <div id="period-month-fields" class="flex gap-2 items-end">
                        <div>
                            <label class="block text-theme-xs text-gray-500 dark:text-gray-400">Tháng</label>
                            <select name="month" class="mt-1 rounded border border-gray-300 px-2 py-1.5 text-theme-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                @for($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" {{ (int) old('month', $now->month) === $m ? 'selected' : '' }}>T{{ $m }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-theme-xs text-gray-500 dark:text-gray-400">Năm</label>
                            <input type="number" name="year" value="{{ old('year', $now->year) }}" min="2020" max="2100" class="mt-1 w-20 rounded border border-gray-300 px-2 py-1.5 text-theme-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>
                    <div id="period-custom-fields" class="hidden flex gap-2 items-end">
                        <div>
                            <label class="block text-theme-xs text-gray-500 dark:text-gray-400">Từ ngày</label>
                            <input type="date" name="period_start" value="{{ old('period_start', '') }}" class="mt-1 rounded border border-gray-300 px-2 py-1.5 text-theme-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-theme-xs text-gray-500 dark:text-gray-400">Đến ngày</label>
                            <input type="date" name="period_end" value="{{ old('period_end', '') }}" class="mt-1 rounded border border-gray-300 px-2 py-1.5 text-theme-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>
                </div>

                <div>
                    <label for="amount_limit_vnd" class="block text-theme-sm font-medium text-gray-700 dark:text-gray-300">Số tiền ngưỡng (₫)</label>
                    <input type="text" name="amount_limit_vnd" id="amount_limit_vnd" value="{{ old('amount_limit_vnd', '') }}" required inputmode="numeric" data-format-vnd
                        class="mt-1 block w-full max-w-xs rounded-lg border border-gray-300 px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        placeholder="VD: 5.000.000">
                    @error('amount_limit_vnd')
                        <p class="mt-1 text-theme-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="modalThemNguong = false" class="rounded-lg border border-gray-300 px-4 py-2 text-theme-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Hủy</button>
                    <button type="submit" id="modal-nguong-submit-btn" class="rounded-lg bg-brand-600 px-4 py-2 text-theme-sm font-medium text-white hover:bg-brand-700 dark:bg-brand-500 dark:hover:bg-brand-600">Tạo ngưỡng</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Thêm/Sửa mục tiêu thu --}}
    <div x-show="modalThemMucTieu" x-cloak class="fixed inset-0 z-[100000] flex items-center justify-center overflow-y-auto p-4" @keydown.escape.window="modalThemMucTieu = false">
        <div x-show="modalThemMucTieu" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-gray-500/60 dark:bg-gray-900/70" @click="modalThemMucTieu = false"></div>
        <div x-show="modalThemMucTieu" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
            class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-800 dark:text-white">
            <div class="flex items-center justify-between mb-4">
                <h3 id="modal-muc-tieu-title" class="text-lg font-semibold text-gray-900 dark:text-white">Thêm mục tiêu thu</h3>
                <button type="button" @click="modalThemMucTieu = false" class="rounded-full p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form id="form-muc-tieu-modal" action="{{ route('tai-chinh.muc-tieu-thu.store') }}" method="post" class="space-y-4">
                @csrf
                <div>
                    <label for="muc_tieu_name" class="block text-theme-sm font-medium text-gray-700 dark:text-gray-300">Tên mục tiêu</label>
                    <input type="text" name="name" id="muc_tieu_name" value="{{ old('name', '') }}" required maxlength="255"
                        class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        placeholder="VD: Thu nhập phụ T1">
                    @error('name') <p class="mt-1 text-theme-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div class="rounded-lg border-2 border-dashed border-gray-300 bg-gray-50/50 p-4 dark:border-gray-600 dark:bg-gray-800/50">
                    <div class="flex flex-wrap gap-4">
                        <div class="min-w-0 flex-1">
                            <p class="mb-2 text-theme-xs font-medium text-gray-600 dark:text-gray-400">Danh mục thu</p>
                            <div class="min-h-[60px] max-h-40 overflow-y-auto space-y-1.5 rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-600 dark:bg-gray-700/50">
                                @forelse($incomeUserCats as $uc)
                                    <label class="flex items-center gap-2 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600/50 rounded px-2 py-1 -mx-2 -my-0.5">
                                        <input type="checkbox" data-type="user_category" data-id="{{ $uc->id }}" class="goal-cat-binding rounded border-gray-400 text-emerald-600 focus:ring-emerald-500" {{ in_array($uc->id, $goalBindIds ?? []) ? 'checked' : '' }}>
                                        <span class="text-theme-sm text-gray-800 dark:text-gray-200">{{ $uc->name }}</span>
                                    </label>
                                @empty
                                    <p class="text-theme-xs text-gray-500 dark:text-gray-400 italic">Chưa có danh mục thu.</p>
                                @endforelse
                            </div>
                            <p class="mt-2 text-theme-xs text-gray-500 dark:text-gray-400">Đã chọn: <span id="goal-cat-count">0</span> mục</p>
                            <input type="hidden" name="category_bindings" id="goal_category_bindings_json" value="">
                            @error('category_bindings') <p class="mt-1 text-theme-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="mb-2 text-theme-xs font-medium text-gray-600 dark:text-gray-400">Danh mục chi</p>
                            <div class="min-h-[60px] max-h-40 overflow-y-auto space-y-1.5 rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-600 dark:bg-gray-700/50">
                                @forelse($expenseUserCats as $uc)
                                    <label class="flex items-center gap-2 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600/50 rounded px-2 py-1 -mx-2 -my-0.5">
                                        <input type="checkbox" data-type="user_category" data-id="{{ $uc->id }}" class="goal-expense-cat-binding rounded border-gray-400 text-amber-600 focus:ring-amber-500" {{ in_array($uc->id, $goalExpenseBindIds ?? []) ? 'checked' : '' }}>
                                        <span class="text-theme-sm text-gray-800 dark:text-gray-200">{{ $uc->name }}</span>
                                    </label>
                                @empty
                                    <p class="text-theme-xs text-gray-500 dark:text-gray-400 italic">Chưa có danh mục chi.</p>
                                @endforelse
                            </div>
                            <p class="mt-2 text-theme-xs text-gray-500 dark:text-gray-400">Đã chọn chi: <span id="goal-expense-cat-count">0</span> mục</p>
                            <input type="hidden" name="expense_category_bindings" id="goal_expense_category_bindings_json" value="">
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-4">
                    <div>
                        <label for="goal_period_type" class="block text-theme-sm font-medium text-gray-700 dark:text-gray-300">Kỳ</label>
                        <select name="period_type" id="goal_period_type" class="mt-1 rounded-lg border border-gray-300 px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="month" {{ old('period_type', 'month') === 'month' ? 'selected' : '' }}>Theo tháng</option>
                            <option value="custom" {{ old('period_type', '') === 'custom' ? 'selected' : '' }}>Tùy chọn</option>
                        </select>
                    </div>
                    <div id="goal-period-month" class="flex gap-2 items-end">
                        <div>
                            <label class="block text-theme-xs text-gray-500 dark:text-gray-400">Tháng</label>
                            <select name="month" class="mt-1 rounded border border-gray-300 px-2 py-1.5 text-theme-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                @for($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" {{ (int) old('month', $now->month) === $m ? 'selected' : '' }}>T{{ $m }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-theme-xs text-gray-500 dark:text-gray-400">Năm</label>
                            <input type="number" name="year" value="{{ old('year', $now->year) }}" min="2020" max="2100" class="mt-1 w-20 rounded border border-gray-300 px-2 py-1.5 text-theme-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>
                    <div id="goal-period-custom" class="hidden flex gap-2 items-end">
                        <div>
                            <label class="block text-theme-xs text-gray-500 dark:text-gray-400">Từ ngày</label>
                            <input type="date" name="period_start" value="{{ old('period_start', '') }}" class="mt-1 rounded border border-gray-300 px-2 py-1.5 text-theme-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-theme-xs text-gray-500 dark:text-gray-400">Đến ngày</label>
                            <input type="date" name="period_end" value="{{ old('period_end', '') }}" class="mt-1 rounded border border-gray-300 px-2 py-1.5 text-theme-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>
                </div>
                <div>
                    <label for="amount_target_vnd" class="block text-theme-sm font-medium text-gray-700 dark:text-gray-300">Số tiền mục tiêu (₫)</label>
                    <input type="text" name="amount_target_vnd" id="amount_target_vnd" value="{{ old('amount_target_vnd', '') }}" required inputmode="numeric" data-format-vnd
                        class="mt-1 block w-full max-w-xs rounded-lg border border-gray-300 px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        placeholder="VD: 10.000.000">
                    @error('amount_target_vnd') <p class="mt-1 text-theme-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="modalThemMucTieu = false" class="rounded-lg border border-gray-300 px-4 py-2 text-theme-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Hủy</button>
                    <button type="submit" id="modal-muc-tieu-submit-btn" class="rounded-lg bg-emerald-600 px-4 py-2 text-theme-sm font-medium text-white hover:bg-emerald-700 dark:bg-emerald-500 dark:hover:bg-emerald-600">Tạo mục tiêu</button>
                </div>
            </form>
        </div>
    </div>

    <x-ui.confirm-delete openVar="showConfirmDelete" title="Xác nhận xóa" defaultMessage="Bạn có chắc muốn xóa? Hành động không thể hoàn tác." />

    <style>[x-cloak]{display:none !important}</style>
</div>

@push('scripts')
<script>
(function() {
    var periodType = document.getElementById('period_type');
    var monthFields = document.getElementById('period-month-fields');
    var customFields = document.getElementById('period-custom-fields');
    if (periodType) {
        periodType.addEventListener('change', function() {
            var isMonth = this.value === 'month';
            monthFields.classList.toggle('hidden', !isMonth);
            customFields.classList.toggle('hidden', isMonth);
        });
        periodType.dispatchEvent(new Event('change'));
    }

    var goalPeriodType = document.getElementById('goal_period_type');
    var goalMonthFields = document.getElementById('goal-period-month');
    var goalCustomFields = document.getElementById('goal-period-custom');
    if (goalPeriodType) {
        goalPeriodType.addEventListener('change', function() {
            var isMonth = this.value === 'month';
            goalMonthFields.classList.toggle('hidden', !isMonth);
            goalCustomFields.classList.toggle('hidden', isMonth);
        });
        goalPeriodType.dispatchEvent(new Event('change'));
    }

    function updateGoalBindings() {
        var checks = document.querySelectorAll('.goal-cat-binding:checked');
        var arr = [];
        checks.forEach(function(c) {
            arr.push({ type: c.getAttribute('data-type'), id: parseInt(c.getAttribute('data-id'), 10) });
        });
        var inp = document.getElementById('goal_category_bindings_json');
        if (inp) inp.value = JSON.stringify(arr);
        var countEl = document.getElementById('goal-cat-count');
        if (countEl) countEl.textContent = arr.length;
    }
    function updateGoalExpenseBindings() {
        var checks = document.querySelectorAll('.goal-expense-cat-binding:checked');
        var arr = [];
        checks.forEach(function(c) {
            arr.push({ type: c.getAttribute('data-type'), id: parseInt(c.getAttribute('data-id'), 10) });
        });
        var inp = document.getElementById('goal_expense_category_bindings_json');
        if (inp) inp.value = JSON.stringify(arr);
        var countEl = document.getElementById('goal-expense-cat-count');
        if (countEl) countEl.textContent = arr.length;
    }
    document.querySelectorAll('.goal-cat-binding').forEach(function(el) { el.addEventListener('change', updateGoalBindings); });
    document.querySelectorAll('.goal-expense-cat-binding').forEach(function(el) { el.addEventListener('change', updateGoalExpenseBindings); });
    updateGoalBindings();
    updateGoalExpenseBindings();
    document.querySelectorAll('form[action*="muc-tieu-thu"]').forEach(function(f) {
        f.addEventListener('submit', function() { updateGoalBindings(); updateGoalExpenseBindings(); });
    });

    function updateCategoryBindings() {
        var checks = document.querySelectorAll('.cat-binding:checked');
        var arr = [];
        checks.forEach(function(c) {
            arr.push({ type: c.getAttribute('data-type'), id: parseInt(c.getAttribute('data-id'), 10) });
        });
        var inp = document.getElementById('category_bindings_json');
        if (inp) inp.value = JSON.stringify(arr);
        var countEl = document.getElementById('cat-binding-count');
        if (countEl) countEl.textContent = arr.length;
    }
    document.querySelectorAll('.cat-binding').forEach(function(el) {
        el.addEventListener('change', updateCategoryBindings);
    });
    updateCategoryBindings();

    var wrap = document.getElementById('nguong-ngan-sach-wrap');
    var nguongStoreUrl = wrap ? (wrap.getAttribute('data-nguong-store-url') || '') : '';
    var nguongUpdateUrlTpl = wrap ? (wrap.getAttribute('data-nguong-update-url') || '') : '';
    var mucTieuStoreUrl = wrap ? (wrap.getAttribute('data-muc-tieu-store-url') || '') : '';
    var mucTieuUpdateUrlTpl = wrap ? (wrap.getAttribute('data-muc-tieu-update-url') || '') : '';

    function resetFormNguong() {
        var form = document.getElementById('form-nguong-modal');
        if (!form) return;
        form.action = nguongStoreUrl;
        var methodInp = form.querySelector('input[name="_method"]');
        if (methodInp) methodInp.remove();
        var nameInp = document.getElementById('nguong_name');
        if (nameInp) nameInp.value = '';
        var amountInp = document.getElementById('amount_limit_vnd');
        if (amountInp) { amountInp.value = ''; amountInp.dispatchEvent(new Event('input', { bubbles: true })); }
        var periodType = document.getElementById('period_type');
        if (periodType) periodType.value = 'month';
        var monthSel = form.querySelector('select[name="month"]');
        var yearInp = form.querySelector('input[name="year"]');
        var now = new Date();
        if (monthSel) monthSel.value = String(now.getMonth() + 1);
        if (yearInp) yearInp.value = String(now.getFullYear());
        var startInp = form.querySelector('input[name="period_start"]');
        var endInp = form.querySelector('input[name="period_end"]');
        if (startInp) startInp.value = '';
        if (endInp) endInp.value = '';
        document.querySelectorAll('.cat-binding').forEach(function(cb) { cb.checked = false; });
        updateCategoryBindings();
        var titleEl = document.getElementById('modal-nguong-title');
        var btnEl = document.getElementById('modal-nguong-submit-btn');
        if (titleEl) titleEl.textContent = 'Thêm ngưỡng';
        if (btnEl) btnEl.textContent = 'Tạo ngưỡng';
        if (periodType) periodType.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function resetFormMucTieu() {
        var form = document.getElementById('form-muc-tieu-modal');
        if (!form) return;
        form.action = mucTieuStoreUrl;
        var methodInp = form.querySelector('input[name="_method"]');
        if (methodInp) methodInp.remove();
        var nameInp = document.getElementById('muc_tieu_name');
        if (nameInp) nameInp.value = '';
        var amountInp = document.getElementById('amount_target_vnd');
        if (amountInp) { amountInp.value = ''; amountInp.dispatchEvent(new Event('input', { bubbles: true })); }
        var periodType = document.getElementById('goal_period_type');
        if (periodType) periodType.value = 'month';
        var monthSel = form.querySelector('select[name="month"]');
        var yearInp = form.querySelector('input[name="year"]');
        var now = new Date();
        if (monthSel) monthSel.value = String(now.getMonth() + 1);
        if (yearInp) yearInp.value = String(now.getFullYear());
        var startInp = form.querySelector('input[name="period_start"]');
        var endInp = form.querySelector('input[name="period_end"]');
        if (startInp) startInp.value = '';
        if (endInp) endInp.value = '';
        document.querySelectorAll('.goal-cat-binding').forEach(function(cb) { cb.checked = false; });
        document.querySelectorAll('.goal-expense-cat-binding').forEach(function(cb) { cb.checked = false; });
        updateGoalBindings();
        updateGoalExpenseBindings();
        var titleEl = document.getElementById('modal-muc-tieu-title');
        var btnEl = document.getElementById('modal-muc-tieu-submit-btn');
        if (titleEl) titleEl.textContent = 'Thêm mục tiêu thu';
        if (btnEl) btnEl.textContent = 'Tạo mục tiêu';
        if (periodType) periodType.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function fillFormNguong(d) {
        var form = document.getElementById('form-nguong-modal');
        if (!form || !d) return;
        var updateUrl = nguongUpdateUrlTpl.replace(/\/0$/, '/' + d.id);
        form.setAttribute('action', updateUrl);
        form.action = updateUrl;
        var methodInp = form.querySelector('input[name="_method"]');
        if (!methodInp) { methodInp = document.createElement('input'); methodInp.setAttribute('type', 'hidden'); methodInp.setAttribute('name', '_method'); form.appendChild(methodInp); }
        methodInp.setAttribute('value', 'PUT');
        methodInp.value = 'PUT';
        var nameInp = document.getElementById('nguong_name');
        if (nameInp) nameInp.value = d.name || '';
        var amountInp = document.getElementById('amount_limit_vnd');
        if (amountInp) { amountInp.value = String(d.amount_limit_vnd || ''); amountInp.dispatchEvent(new Event('input', { bubbles: true })); }
        var periodType = document.getElementById('period_type');
        if (periodType) periodType.value = d.period_type || 'month';
        var monthSel = form.querySelector('select[name="month"]');
        var yearInp = form.querySelector('input[name="year"]');
        if (monthSel && d.month) monthSel.value = String(d.month);
        if (yearInp && d.year) yearInp.value = String(d.year);
        var startInp = form.querySelector('input[name="period_start"]');
        var endInp = form.querySelector('input[name="period_end"]');
        if (startInp) startInp.value = d.period_start || '';
        if (endInp) endInp.value = d.period_end || '';
        var ids = (d.category_bindings || []).map(function(b) { return parseInt(b.id, 10); });
        document.querySelectorAll('.cat-binding').forEach(function(cb) {
            cb.checked = ids.indexOf(parseInt(cb.getAttribute('data-id'), 10)) >= 0;
        });
        updateCategoryBindings();
        var titleEl = document.getElementById('modal-nguong-title');
        var btnEl = document.getElementById('modal-nguong-submit-btn');
        if (titleEl) titleEl.textContent = 'Sửa ngưỡng';
        if (btnEl) btnEl.textContent = 'Cập nhật';
        if (periodType) periodType.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function fillFormMucTieu(d) {
        var form = document.getElementById('form-muc-tieu-modal');
        if (!form || !d) return;
        var updateUrl = mucTieuUpdateUrlTpl.replace(/\/0$/, '/' + d.id);
        form.setAttribute('action', updateUrl);
        form.action = updateUrl;
        var methodInp = form.querySelector('input[name="_method"]');
        if (!methodInp) { methodInp = document.createElement('input'); methodInp.setAttribute('type', 'hidden'); methodInp.setAttribute('name', '_method'); form.appendChild(methodInp); }
        methodInp.setAttribute('value', 'PUT');
        methodInp.value = 'PUT';
        var nameInp = document.getElementById('muc_tieu_name');
        if (nameInp) nameInp.value = d.name || '';
        var amountInp = document.getElementById('amount_target_vnd');
        if (amountInp) { amountInp.value = String(d.amount_target_vnd || ''); amountInp.dispatchEvent(new Event('input', { bubbles: true })); }
        var periodType = document.getElementById('goal_period_type');
        if (periodType) periodType.value = d.period_type || 'month';
        var monthSel = form.querySelector('select[name="month"]');
        var yearInp = form.querySelector('input[name="year"]');
        if (monthSel && d.month) monthSel.value = String(d.month);
        if (yearInp && d.year) yearInp.value = String(d.year);
        var startInp = form.querySelector('input[name="period_start"]');
        var endInp = form.querySelector('input[name="period_end"]');
        if (startInp) startInp.value = d.period_start || '';
        if (endInp) endInp.value = d.period_end || '';
        var ids = (d.category_bindings || []).map(function(b) { return parseInt(b.id, 10); });
        document.querySelectorAll('.goal-cat-binding').forEach(function(cb) {
            cb.checked = ids.indexOf(parseInt(cb.getAttribute('data-id'), 10)) >= 0;
        });
        var expIds = (d.expense_category_bindings || []).map(function(b) { return parseInt(b.id, 10); });
        document.querySelectorAll('.goal-expense-cat-binding').forEach(function(cb) {
            cb.checked = expIds.indexOf(parseInt(cb.getAttribute('data-id'), 10)) >= 0;
        });
        updateGoalBindings();
        updateGoalExpenseBindings();
        var titleEl = document.getElementById('modal-muc-tieu-title');
        var btnEl = document.getElementById('modal-muc-tieu-submit-btn');
        if (titleEl) titleEl.textContent = 'Sửa mục tiêu thu';
        if (btnEl) btnEl.textContent = 'Cập nhật';
        if (periodType) periodType.dispatchEvent(new Event('change', { bubbles: true }));
    }

    document.addEventListener('open-nguong-modal-add', function() { resetFormNguong(); window.dispatchEvent(new CustomEvent('open-nguong-modal')); });
    document.addEventListener('open-muc-tieu-modal-add', function() { resetFormMucTieu(); window.dispatchEvent(new CustomEvent('open-muc-tieu-modal')); });

    document.addEventListener('click', function(e) {
        var a = e.target.closest('a[href*="nguong-ngan-sach"]');
        if (!a || !a.href) return;
        var href = a.getAttribute('href') || '';
        if (href.indexOf('edit=') === -1 && href.indexOf('edit_goal=') === -1) return;
        e.preventDefault();
        try {
            var u = new URL(a.href, window.location.origin);
            var editId = u.searchParams.get('edit');
            var editGoalId = u.searchParams.get('edit_goal');
            if (editId) {
                var editUrl = nguongUpdateUrlTpl.replace(/\/0$/, '/' + editId) + '/edit';
                fetch(editUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                    .then(function(r) { return r.json(); })
                    .then(function(json) {
                        if (json.success && json.data) { fillFormNguong(json.data); window.dispatchEvent(new CustomEvent('open-nguong-modal')); }
                        else showNguongMessage(json.message || 'Không tải được dữ liệu.', true);
                    })
                    .catch(function() { showNguongMessage('Lỗi kết nối.', true); });
                return;
            }
            if (editGoalId) {
                var goalEditUrl = mucTieuUpdateUrlTpl.replace(/\/0$/, '/' + editGoalId) + '/edit';
                fetch(goalEditUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                    .then(function(r) { return r.json(); })
                    .then(function(json) {
                        if (json.success && json.data) { fillFormMucTieu(json.data); window.dispatchEvent(new CustomEvent('open-muc-tieu-modal')); }
                        else showNguongMessage(json.message || 'Không tải được dữ liệu.', true);
                    })
                    .catch(function() { showNguongMessage('Lỗi kết nối.', true); });
            }
        } catch (err) {}
    });

    function showNguongMessage(text, isError) {
        var el = document.getElementById('nguong-ajax-message');
        if (!el) return;
        el.textContent = text;
        el.classList.remove('hidden', 'border-green-200', 'bg-green-50', 'text-green-800', 'dark:border-green-800', 'dark:bg-green-900/20', 'dark:text-green-400', 'border-red-200', 'bg-red-50', 'text-red-700', 'dark:border-red-800', 'dark:bg-red-900/20', 'dark:text-red-400');
        var classes = (isError ? 'border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400' : 'border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400').split(/\s+/);
        el.classList.add.apply(el.classList, classes);
        el.classList.remove('hidden');
        setTimeout(function() { el.classList.add('hidden'); }, 5000);
    }

    document.addEventListener('submit', function(e) {
        var form = e.target;
        if (form.tagName !== 'FORM') return;
        var action = (form.action || form.getAttribute('action') || '');
        if (action.indexOf('nguong-ngan-sach') === -1 && action.indexOf('muc-tieu-thu') === -1) return;
        e.preventDefault();
        if (action.indexOf('nguong-ngan-sach') !== -1) updateCategoryBindings();
        else { updateGoalBindings(); updateGoalExpenseBindings(); }
        form.querySelectorAll('[data-format-vnd]').forEach(function(inp) {
            var v = (inp.value || '').replace(/\s/g, '').replace(/\D/g, '');
            if (inp.getAttribute('data-format-vnd-allow-negative') === '1' && (inp.value || '').trim().indexOf('-') === 0) v = '-' + v;
            inp.value = v;
        });
        var fd = new FormData(form);
        var isDestroy = (form.querySelector('input[name="_method"]') || {}).value === 'DELETE';
        var submitUrl = form.action || action;
        fetch(submitUrl, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function(r) {
                var ct = (r.headers.get('Content-Type') || '');
                if (ct.indexOf('application/json') !== -1) {
                    return r.json().then(function(data) { return { ok: r.ok, status: r.status, data: data }; });
                }
                return r.text().then(function(t) { return { ok: false, status: r.status, data: { message: r.status === 419 ? 'Phiên hết hạn. Vui lòng tải lại trang.' : ('Lỗi ' + r.status) } }; });
            })
            .then(function(res) {
                if (res.ok && res.data && res.data.success) {
                    showNguongMessage(res.data.message || 'Đã lưu.', false);
                    if (!isDestroy) {
                        if (action.indexOf('nguong-ngan-sach') !== -1) window.dispatchEvent(new CustomEvent('nguong-saved'));
                        else window.dispatchEvent(new CustomEvent('muc-tieu-saved'));
                    }
                    if (typeof window.fetchNguongList === 'function') window.fetchNguongList();
                } else {
                    var errMsg = (res.data && (res.data.message || (res.data.errors && Object.values(res.data.errors).flat().length))) ? (res.data.message || Object.values(res.data.errors).flat().join(' ')) : 'Đã xảy ra lỗi.';
                    showNguongMessage(errMsg, true);
                }
            })
            .catch(function() { showNguongMessage('Lỗi kết nối. Vui lòng thử lại.', true); });
        return false;
    });

    document.querySelectorAll('form[action*="nguong-ngan-sach"]').forEach(function(f) {
        f.addEventListener('submit', function() { updateCategoryBindings(); });
    });
})();

    (function() {
        var form = document.getElementById('form-nguong-filter');
        var container = document.getElementById('nguong-ngan-sach-container');
        var countEl = document.getElementById('nguong-count');
        if (!form || !container) return;
        var tableUrl = (container.getAttribute('data-table-url') || '').replace(/\/+$/, '');
        if (!tableUrl) return;

        var pctEl = document.getElementById('filter_pct');
        var vuotEl = document.getElementById('filter_vuot');
        var hethanEl = document.getElementById('filter_het_han');
        var btnPct = document.getElementById('btn-nguong-pct');
        var btnVuot = document.getElementById('btn-nguong-vuot');
        var btnHethan = document.getElementById('btn-nguong-hethan');
        var btnXoaLoc = document.getElementById('btn-nguong-xoa-loc');

        var pctLabels = { '': 'Tất cả %', 'under60': 'Dưới 60%', '60_80': '60-80%', '80_100': '80-100%', 'over100': 'Trên 100%' };

        function buildParams() {
            var params = new URLSearchParams();
            if (pctEl && pctEl.value) params.set('filter_pct', pctEl.value);
            if (vuotEl && vuotEl.value === '1') params.set('filter_vuot', '1');
            if (hethanEl && hethanEl.value === '1') params.set('filter_het_han', '1');
            return params;
        }

        function hasFilter() {
            return (pctEl && pctEl.value !== '') || (vuotEl && vuotEl.value === '1') || (hethanEl && hethanEl.value === '1');
        }

        function updateButtonStates() {
            if (btnXoaLoc) btnXoaLoc.style.display = hasFilter() ? '' : 'none';
            if (btnPct) {
                btnPct.textContent = pctLabels[pctEl ? pctEl.value : ''] || 'Tất cả %';
                btnPct.classList.toggle('text-gray-400', !pctEl || !pctEl.value);
                btnPct.classList.toggle('dark:text-gray-500', !pctEl || !pctEl.value);
                btnPct.classList.toggle('text-gray-700', pctEl && pctEl.value);
                btnPct.classList.toggle('dark:text-gray-200', pctEl && pctEl.value);
            }
            if (btnVuot) {
                btnVuot.classList.toggle('font-semibold', vuotEl && vuotEl.value === '1');
                btnVuot.classList.toggle('text-gray-700', vuotEl && vuotEl.value === '1');
                btnVuot.classList.toggle('dark:text-gray-200', vuotEl && vuotEl.value === '1');
                btnVuot.classList.toggle('text-gray-400', !vuotEl || vuotEl.value !== '1');
                btnVuot.classList.toggle('dark:text-gray-500', !vuotEl || vuotEl.value !== '1');
            }
            if (btnHethan) {
                btnHethan.classList.toggle('font-semibold', hethanEl && hethanEl.value === '1');
                btnHethan.classList.toggle('text-gray-700', hethanEl && hethanEl.value === '1');
                btnHethan.classList.toggle('dark:text-gray-200', hethanEl && hethanEl.value === '1');
                btnHethan.classList.toggle('text-gray-400', !hethanEl || hethanEl.value !== '1');
                btnHethan.classList.toggle('dark:text-gray-500', !hethanEl || hethanEl.value !== '1');
            }
        }

        function syncUrl() {
            var params = buildParams();
            params.set('tab', 'nguong-ngan-sach');
            var q = params.toString();
            var newUrl = window.location.pathname + (q ? '?' + q : '');
            if (window.location.pathname + (window.location.search || '') !== newUrl) {
                history.replaceState(null, '', newUrl);
            }
        }

        function fetchList() {
            var url = tableUrl + '?' + buildParams().toString();
            container.classList.add('opacity-70');
            fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'text/html' } })
                .then(function(r) { return r.ok ? r.text() : Promise.reject(); })
                .then(function(html) {
                    var wrap = document.createElement('div');
                    wrap.innerHTML = html.trim();
                    var inner = wrap.querySelector('[data-ajax-container]');
                    if (inner) {
                        container.innerHTML = inner.innerHTML;
                        var countText = inner.getAttribute('data-count-text');
                        if (countEl && countText !== null) countEl.textContent = countText;
                    }
                    container.classList.remove('opacity-70');
                    syncUrl();
                    updateButtonStates();
                })
                .catch(function() { container.classList.remove('opacity-70'); });
        }

        if (btnPct && pctEl && btnPct.dataset.cycle) {
            var order = btnPct.dataset.cycle.split(',');
            btnPct.addEventListener('click', function() {
                var idx = order.indexOf(pctEl.value);
                idx = (idx + 1) % order.length;
                pctEl.value = order[idx];
                fetchList();
            });
        }
        if (btnVuot && vuotEl) {
            btnVuot.addEventListener('click', function() {
                vuotEl.value = vuotEl.value === '1' ? '' : '1';
                fetchList();
            });
        }
        if (btnHethan && hethanEl) {
            btnHethan.addEventListener('click', function() {
                hethanEl.value = hethanEl.value === '1' ? '' : '1';
                fetchList();
            });
        }
        if (btnXoaLoc) {
            btnXoaLoc.addEventListener('click', function() {
                if (pctEl) pctEl.value = '';
                if (vuotEl) vuotEl.value = '';
                if (hethanEl) hethanEl.value = '';
                fetchList();
            });
        }
        window.fetchNguongList = fetchList;
        var parent = container.parentElement;
        if (parent) {
            parent.addEventListener('click', function(e) {
                if (e.target.id === 'nguong-xoa-loc-inline') {
                    e.preventDefault();
                    if (pctEl) pctEl.value = '';
                    if (vuotEl) vuotEl.value = '';
                    if (hethanEl) hethanEl.value = '';
                    fetchList();
                }
            });
        }
    })();
</script>
@endpush
