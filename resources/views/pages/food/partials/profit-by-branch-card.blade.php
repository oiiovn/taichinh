@php
    $profitByBranch = $profitByBranch ?? collect();
    $branchThemes = [
        ['box' => 'bg-emerald-50 dark:bg-emerald-900/30', 'icon' => 'text-emerald-600 dark:text-emerald-400', 'ring' => 'ring-emerald-100 dark:ring-emerald-800/50'],
        ['box' => 'bg-amber-50 dark:bg-amber-900/30', 'icon' => 'text-amber-600 dark:text-amber-400', 'ring' => 'ring-amber-100 dark:ring-amber-800/50'],
        ['box' => 'bg-rose-50 dark:bg-rose-900/30', 'icon' => 'text-rose-600 dark:text-rose-400', 'ring' => 'ring-rose-100 dark:ring-rose-800/50'],
        ['box' => 'bg-sky-50 dark:bg-sky-900/30', 'icon' => 'text-sky-600 dark:text-sky-400', 'ring' => 'ring-sky-100 dark:ring-sky-800/50'],
        ['box' => 'bg-violet-50 dark:bg-violet-900/30', 'icon' => 'text-violet-600 dark:text-violet-400', 'ring' => 'ring-violet-100 dark:ring-violet-800/50'],
    ];
@endphp
<div class="flex h-full flex-col overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
    <div class="border-b border-gray-100 px-4 pb-4 pt-5 dark:border-gray-800 sm:px-5">
        <div class="flex items-start gap-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 ring-1 ring-emerald-100 dark:bg-emerald-900/30 dark:ring-emerald-800/50">
                <svg class="h-6 w-6 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/>
                </svg>
            </div>
            <div class="min-w-0">
                <h3 class="text-sm font-bold leading-snug text-gray-900 dark:text-white">
                    Báo cáo lợi nhuận <span class="text-emerald-600 dark:text-emerald-400">theo chi nhánh</span>
                </h3>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Tổng hợp lợi nhuận theo quyết toán</p>
            </div>
        </div>
    </div>

    @if($profitByBranch->isNotEmpty())
        <div class="overflow-x-auto px-2 sm:px-4">
            {{-- Column headers --}}
            <div class="grid min-w-[640px] grid-cols-[minmax(0,1.6fr)_minmax(0,0.9fr)_minmax(0,1.1fr)_minmax(0,1.2fr)] items-center gap-3 border-b border-gray-100 px-3 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:text-gray-400">
                <div class="flex items-center gap-2">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </span>
                    Chi nhánh
                </div>
                <div class="flex items-center justify-center gap-2">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-violet-50 text-violet-600 dark:bg-violet-900/30 dark:text-violet-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </span>
                    Số báo cáo
                </div>
                <div class="flex items-center justify-end gap-2">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    </span>
                    Quyết toán
                </div>
                <div class="flex items-center justify-end gap-2">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                    </span>
                    Lợi nhuận
                </div>
            </div>

            <ul class="min-w-[640px] divide-y divide-gray-100 dark:divide-gray-800">
                @foreach($profitByBranch as $row)
                    @php
                        $theme = $branchThemes[$loop->index % count($branchThemes)];
                        $positive = ($row['loi_nhuan'] ?? 0) >= 0;
                    @endphp
                    <li class="grid grid-cols-[minmax(0,1.6fr)_minmax(0,0.9fr)_minmax(0,1.1fr)_minmax(0,1.2fr)] items-center gap-3 px-3 py-3.5 transition hover:bg-gray-50/80 dark:hover:bg-gray-800/40">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ring-1 {{ $theme['box'] }} {{ $theme['ring'] }}">
                                <svg class="h-5 w-5 {{ $theme['icon'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 9.75L12 4l9 5.75V19a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1V9.75z"/>
                                </svg>
                            </span>
                            <span class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $row['branch_name'] }}</span>
                        </div>
                        <div class="flex justify-center">
                            <span class="inline-flex min-w-[2.25rem] items-center justify-center rounded-lg bg-violet-50 px-2.5 py-1 text-sm font-bold tabular-nums text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                                {{ $row['report_count'] }}
                            </span>
                        </div>
                        <div class="text-right text-sm font-semibold tabular-nums text-gray-900 dark:text-white">
                            {{ $fmt($row['quyet_toan']) }} đ
                        </div>
                        <div class="flex justify-end">
                            <span @class([
                                'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-bold tabular-nums',
                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' => $positive,
                                'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300' => ! $positive,
                            ])>
                                {{ $fmt($row['loi_nhuan']) }} đ
                                <span @class([
                                    'inline-flex h-5 w-5 items-center justify-center rounded-full text-white',
                                    'bg-emerald-600' => $positive,
                                    'bg-rose-600' => ! $positive,
                                ])>
                                    @if($positive)
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                    @else
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                    @endif
                                </span>
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="mt-auto border-t border-gray-100 px-4 py-3 dark:border-gray-800 sm:px-5">
            <p class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                Dữ liệu được cập nhật mới nhất
                @if(isset($updatedAt) && $updatedAt)
                    <span class="text-gray-400">: {{ $updatedAt->format('d/m/Y H:i') }}</span>
                @elseif(isset($from) && isset($to))
                    <span class="text-gray-400">: {{ $to->format('d/m/Y') }}</span>
                @endif
            </p>
        </div>
    @else
        <div class="px-6 py-10 text-center">
            <p class="text-sm text-gray-500 dark:text-gray-400">Không có dữ liệu lợi nhuận theo chi nhánh trong kỳ lọc.</p>
        </div>
    @endif
</div>
