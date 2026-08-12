@php
    $chartDoanhSoDates = $chartDoanhSoDates ?? [];
    $chartDoanhSoLoiNhuan = $chartDoanhSoLoiNhuan ?? [];
    $chartDoanhSoQuyetToan = $chartDoanhSoQuyetToan ?? [];

    $totalQuyetToan = array_sum($chartDoanhSoQuyetToan);
    $totalLoiNhuan = array_sum($chartDoanhSoLoiNhuan);
    $dayCount = max(1, count($chartDoanhSoDates));
    $hieuSuat = $totalQuyetToan > 0 ? round($totalLoiNhuan / $totalQuyetToan * 100, 1) : null;

    $trendPct = function (array $values): ?float {
        $n = count($values);
        if ($n < 2) {
            return null;
        }
        $mid = max(1, (int) floor($n / 2));
        $first = array_sum(array_slice($values, 0, $mid));
        $second = array_sum(array_slice($values, $mid));
        if ($first == 0) {
            if ($second > 0) {
                return 100.0;
            }
            if ($second < 0) {
                return -100.0;
            }

            return 0.0;
        }

        return round(($second - $first) / abs($first) * 100, 1);
    };

    $sparkPoints = function (array $values, int $width = 72, int $height = 32): string {
        if (count($values) < 2) {
            return '';
        }
        $min = min($values);
        $max = max($values);
        $range = max(1, $max - $min);
        $points = [];
        foreach ($values as $i => $v) {
            $x = ($i / (count($values) - 1)) * $width;
            $y = $height - (($v - $min) / $range) * ($height - 8) - 4;
            $points[] = round($x, 1).','.round($y, 1);
        }

        return implode(' ', $points);
    };

    $trendQuyetToan = $trendPct($chartDoanhSoQuyetToan);
    $trendLoiNhuan = $trendPct($chartDoanhSoLoiNhuan);
@endphp
<div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
    <div class="rounded-2xl border border-orange-100 bg-white p-4 shadow-sm dark:border-orange-900/40 dark:bg-gray-900">
        <div class="flex items-start justify-between gap-2">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-orange-100 text-orange-600 dark:bg-orange-900/40 dark:text-orange-400">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </span>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Tổng quyết toán</p>
                    <p class="mt-1 text-xl font-bold tabular-nums text-orange-600 dark:text-orange-400">{{ $fmt($totalQuyetToan) }} đ</p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Trong {{ $dayCount }} ngày</p>
                </div>
            </div>
            <div class="flex flex-col items-end gap-1">
                @if($pts = $sparkPoints($chartDoanhSoQuyetToan))
                    <svg viewBox="0 0 72 32" class="h-8 w-16 opacity-80" aria-hidden="true">
                        <polyline fill="none" stroke="#f97316" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="{{ $pts }}"/>
                    </svg>
                @endif
                @if($trendQuyetToan !== null)
                    <span @class([
                        'inline-flex items-center gap-0.5 text-xs font-semibold',
                        'text-emerald-600 dark:text-emerald-400' => $trendQuyetToan >= 0,
                        'text-rose-600 dark:text-rose-400' => $trendQuyetToan < 0,
                    ])>
                        @if($trendQuyetToan >= 0)▲ @else ▼ @endif{{ abs($trendQuyetToan) }}%
                    </span>
                @endif
            </div>
        </div>
    </div>
    <div class="rounded-2xl border border-blue-100 bg-white p-4 shadow-sm dark:border-blue-900/40 dark:bg-gray-900">
        <div class="flex items-start justify-between gap-2">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-100 text-blue-600 dark:bg-blue-900/40 dark:text-blue-400">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </span>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Tổng lợi nhuận</p>
                    <p class="mt-1 text-xl font-bold tabular-nums text-blue-600 dark:text-blue-400">{{ $fmt($totalLoiNhuan) }} đ</p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Trong {{ $dayCount }} ngày</p>
                </div>
            </div>
            <div class="flex flex-col items-end gap-1">
                @if($pts = $sparkPoints($chartDoanhSoLoiNhuan))
                    <svg viewBox="0 0 72 32" class="h-8 w-16 opacity-80" aria-hidden="true">
                        <polyline fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="{{ $pts }}"/>
                    </svg>
                @endif
                @if($trendLoiNhuan !== null)
                    <span @class([
                        'inline-flex items-center gap-0.5 text-xs font-semibold',
                        'text-emerald-600 dark:text-emerald-400' => $trendLoiNhuan >= 0,
                        'text-rose-600 dark:text-rose-400' => $trendLoiNhuan < 0,
                    ])>
                        @if($trendLoiNhuan >= 0)▲ @else ▼ @endif{{ abs($trendLoiNhuan) }}%
                    </span>
                @endif
            </div>
        </div>
    </div>
    <div class="rounded-2xl border border-emerald-100 bg-white p-4 shadow-sm dark:border-emerald-900/40 dark:bg-gray-900">
        <div class="flex items-start justify-between gap-2">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-400">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </span>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Lợi nhuận ròng</p>
                    <p class="mt-1 text-xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{{ $fmt($totalLoiNhuan) }} đ</p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Hiệu suất {{ $hieuSuat !== null ? $hieuSuat.'%' : '—' }}</p>
                </div>
            </div>
            <div class="flex flex-col items-end gap-1">
                @if($pts = $sparkPoints($chartDoanhSoLoiNhuan))
                    <svg viewBox="0 0 72 32" class="h-8 w-16 opacity-80" aria-hidden="true">
                        <polyline fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="{{ $pts }}"/>
                    </svg>
                @endif
                @if($trendLoiNhuan !== null)
                    <span @class([
                        'inline-flex items-center gap-0.5 text-xs font-semibold',
                        'text-emerald-600 dark:text-emerald-400' => $trendLoiNhuan >= 0,
                        'text-rose-600 dark:text-rose-400' => $trendLoiNhuan < 0,
                    ])>
                        @if($trendLoiNhuan >= 0)▲ @else ▼ @endif{{ abs($trendLoiNhuan) }}%
                    </span>
                @endif
            </div>
        </div>
    </div>
</div>
