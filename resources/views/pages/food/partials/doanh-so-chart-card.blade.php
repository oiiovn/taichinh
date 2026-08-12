@php
    $chartDoanhSoDates = $chartDoanhSoDates ?? [];
    $chartDoanhSoLoiNhuan = $chartDoanhSoLoiNhuan ?? [];
    $chartDoanhSoQuyetToan = $chartDoanhSoQuyetToan ?? [];
    $periods = $periods ?? [];
    $period = $period ?? 'thang';
    $fromDateInput = $fromDateInput ?? '';
    $toDateInput = $toDateInput ?? '';

    $chartLabels = array_map(function ($d) {
        try {
            return \Carbon\Carbon::parse($d)->format('d/m');
        } catch (\Throwable) {
            return $d;
        }
    }, $chartDoanhSoDates);

    $totalQuyetToan = array_sum($chartDoanhSoQuyetToan);
    $totalLoiNhuan = array_sum($chartDoanhSoLoiNhuan);
    $dayCount = max(1, count($chartDoanhSoDates));
    $hieuSuat = $totalQuyetToan > 0 ? round($totalLoiNhuan / $totalQuyetToan * 100, 1) : null;

    $sparkPoints = function (array $values, int $width = 88, int $height = 36): string {
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
@endphp
<div class="mb-8 overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900" x-data="{ filterOpen: false }">
    {{-- Header --}}
    <div class="border-b border-gray-100 px-4 py-5 dark:border-gray-800 sm:px-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex items-start gap-3">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-violet-50 ring-1 ring-violet-100 dark:bg-violet-900/30 dark:ring-violet-800/50">
                    <svg class="h-6 w-6 text-violet-600 dark:text-violet-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Quyết toán &amp; lợi nhuận theo ngày</h3>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Theo dõi biến động quyết toán và lợi nhuận trong thời gian thực</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if(isset($from) && isset($to))
                    <div class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                        <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span class="font-medium">{{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }}</span>
                    </div>
                @endif
                <button type="button" @click="filterOpen = !filterOpen" class="inline-flex items-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 transition hover:bg-blue-100 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-300 dark:hover:bg-blue-900/50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    Bộ lọc
                </button>
            </div>
        </div>

        <div x-show="filterOpen" x-cloak x-transition class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/50">
            <div class="mb-3 flex flex-wrap gap-2">
                @foreach($periods as $val => $label)
                    <a href="{{ route('food', ['tab' => 'doanh-so', 'period' => $val]) }}" class="rounded-lg border px-3 py-1.5 text-sm {{ $period === $val ? 'border-brand-500 bg-brand-50 text-brand-600 dark:bg-brand-500/20 dark:text-brand-400' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300' }}">{{ $label }}</a>
                @endforeach
            </div>
            <form method="get" action="{{ route('food') }}" class="flex flex-wrap items-end gap-2">
                <input type="hidden" name="tab" value="doanh-so">
                <input type="hidden" name="period" value="{{ $period }}">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Từ ngày</label>
                    <input type="text" id="food-doanhso-from-date" name="from_date" value="{{ $fromDateInput }}" placeholder="Từ ngày" readonly class="min-h-[38px] w-[120px] cursor-pointer rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Đến ngày</label>
                    <input type="text" id="food-doanhso-to-date" name="to_date" value="{{ $toDateInput }}" placeholder="Đến ngày" readonly class="min-h-[38px] w-[120px] cursor-pointer rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                </div>
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Áp dụng</button>
            </form>
        </div>
    </div>

    {{-- Summary cards --}}
    <div class="grid grid-cols-1 gap-3 border-b border-gray-100 p-4 dark:border-gray-800 sm:grid-cols-3 sm:p-6">
        <div class="relative overflow-hidden rounded-2xl border border-orange-100 bg-gradient-to-br from-orange-50/80 to-white p-4 dark:border-orange-900/40 dark:from-orange-950/20 dark:to-gray-900">
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
                @if($pts = $sparkPoints($chartDoanhSoQuyetToan))
                    <svg viewBox="0 0 88 36" class="h-9 w-20 shrink-0 opacity-80" aria-hidden="true">
                        <polyline fill="none" stroke="#f97316" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="{{ $pts }}"/>
                    </svg>
                @endif
            </div>
        </div>
        <div class="relative overflow-hidden rounded-2xl border border-blue-100 bg-gradient-to-br from-blue-50/80 to-white p-4 dark:border-blue-900/40 dark:from-blue-950/20 dark:to-gray-900">
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
                @if($pts = $sparkPoints($chartDoanhSoLoiNhuan))
                    <svg viewBox="0 0 88 36" class="h-9 w-20 shrink-0 opacity-80" aria-hidden="true">
                        <polyline fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="{{ $pts }}"/>
                    </svg>
                @endif
            </div>
        </div>
        <div class="relative overflow-hidden rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-50/80 to-white p-4 dark:border-emerald-900/40 dark:from-emerald-950/20 dark:to-gray-900">
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
                @if($pts = $sparkPoints($chartDoanhSoLoiNhuan))
                    <svg viewBox="0 0 88 36" class="h-9 w-20 shrink-0 opacity-80" aria-hidden="true">
                        <polyline fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="{{ $pts }}"/>
                    </svg>
                @endif
            </div>
        </div>
    </div>

    {{-- Chart --}}
    <div class="px-2 pb-2 pt-4 sm:px-4">
        <div id="food-doanhso-chart"
            class="min-h-[320px] w-full"
            data-labels="{{ json_encode($chartLabels) }}"
            data-quyettoan="{{ json_encode($chartDoanhSoQuyetToan) }}"
            data-loinhuan="{{ json_encode($chartDoanhSoLoiNhuan) }}"></div>
    </div>

    {{-- Footer note --}}
    <div class="relative mx-4 mb-4 overflow-hidden rounded-xl border border-blue-100 bg-gradient-to-r from-blue-50/90 to-sky-50/50 px-4 py-3 dark:border-blue-900/40 dark:from-blue-950/30 dark:to-gray-900 sm:mx-6">
        <div class="pointer-events-none absolute bottom-0 right-0 hidden h-16 w-24 opacity-40 sm:block" aria-hidden="true">
            <svg viewBox="0 0 96 64" class="h-full w-full text-blue-200 dark:text-blue-900/60" fill="currentColor"><path d="M72 8c8 12 4 28-8 36 10-2 18 4 22 14-14-6-28-2-36 8 6-16 20-28 22-58z"/></svg>
        </div>
        <p class="relative flex items-start gap-2 text-sm text-blue-900 dark:text-blue-200">
            <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900/50 dark:text-blue-300">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
            </span>
            <span><strong class="font-semibold">Ghi chú:</strong> Dữ liệu được cập nhật tự động hàng ngày. Kéo chuột để xem chi tiết từng giai đoạn.</span>
        </p>
    </div>
</div>

<script>
(function renderDoanhSoChart() {
    var el = document.getElementById('food-doanhso-chart');
    if (!el) return;
    if (el._chartRendered) return;
    if (typeof window.ApexCharts === 'undefined') {
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/apexcharts@3.45.0/dist/apexcharts.min.js';
        s.onload = function() { el._chartRendered = false; renderDoanhSoChart(); };
        document.head.appendChild(s);
        return;
    }
    el._chartRendered = true;
    var labels = JSON.parse(el.getAttribute('data-labels') || '[]');
    var quyetToan = JSON.parse(el.getAttribute('data-quyettoan') || '[]');
    var loiNhuan = JSON.parse(el.getAttribute('data-loinhuan') || '[]');
    var isDark = document.documentElement.classList.contains('dark');
    new window.ApexCharts(el, {
        series: [
            { name: 'Quyết toán', data: quyetToan },
            { name: 'Lợi nhuận', data: loiNhuan }
        ],
        chart: {
            type: 'area',
            height: 320,
            width: '100%',
            toolbar: { show: false },
            zoom: { enabled: false },
            fontFamily: 'inherit',
        },
        colors: ['#f59e0b', '#3b82f6'],
        stroke: { curve: 'smooth', width: 2.5 },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.35,
                opacityTo: 0.04,
                stops: [0, 90, 100],
            },
        },
        markers: {
            size: 4,
            strokeWidth: 2,
            strokeColors: '#fff',
            hover: { size: 6 },
        },
        dataLabels: { enabled: false },
        grid: {
            borderColor: isDark ? '#374151' : '#e5e7eb',
            strokeDashArray: 4,
            padding: { left: 8, right: 16, top: 8, bottom: 0 },
        },
        xaxis: {
            categories: labels,
            tickAmount: labels.length > 20 ? 12 : undefined,
            labels: { style: { fontSize: '11px' } },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: {
            labels: {
                style: { fontSize: '11px' },
                formatter: function(v) { return new Intl.NumberFormat('vi-VN').format(v) + ' đ'; },
            },
        },
        legend: {
            position: 'bottom',
            horizontalAlign: 'center',
            fontSize: '12px',
            markers: { width: 10, height: 10, radius: 10 },
            itemMargin: { horizontal: 12, vertical: 4 },
        },
        tooltip: {
            shared: true,
            intersect: false,
            y: { formatter: function(v) { return new Intl.NumberFormat('vi-VN').format(v) + ' đ'; } },
        },
    }).render();
})();
if (document.readyState === 'complete') setTimeout(renderDoanhSoChart, 0);
else window.addEventListener('load', function() { setTimeout(renderDoanhSoChart, 0); });

(function initDoanhSoDatePickers() {
    function run() {
        if (typeof window.flatpickr === 'undefined') return;
        var fromEl = document.getElementById('food-doanhso-from-date');
        var toEl = document.getElementById('food-doanhso-to-date');
        if (!fromEl || !toEl) return;
        var opts = { dateFormat: 'Y-m-d', allowInput: false, appendTo: document.body, static: false };
        if (window.flatpickr.l10ns && window.flatpickr.l10ns.vn) opts.locale = 'vn';
        window.flatpickr(fromEl, opts);
        window.flatpickr(toEl, opts);
    }
    if (document.readyState === 'complete') run();
    else window.addEventListener('load', run);
})();
</script>
