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
@endphp
<div class="mb-4 overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900" x-data="{ filterOpen: false, chartMode: 'day' }">
    <div class="flex flex-col gap-3 border-b border-gray-100 px-4 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between sm:px-6">
        <h3 class="text-base font-bold text-gray-900 dark:text-white">Quyết toán &amp; lợi nhuận theo ngày</h3>
        <div class="flex flex-wrap items-center gap-2">
            <div class="inline-flex rounded-xl border border-gray-200 bg-gray-50 p-1 dark:border-gray-600 dark:bg-gray-800">
                <button type="button" @click="chartMode = 'day'; window.foodDoanhSoChartSetMode && window.foodDoanhSoChartSetMode('day')" :class="chartMode === 'day' ? 'bg-brand-600 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 dark:text-gray-300'" class="rounded-lg px-3 py-1.5 text-xs font-semibold transition">Theo ngày</button>
                <button type="button" @click="chartMode = 'week'; window.foodDoanhSoChartSetMode && window.foodDoanhSoChartSetMode('week')" :class="chartMode === 'week' ? 'bg-brand-600 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 dark:text-gray-300'" class="rounded-lg px-3 py-1.5 text-xs font-semibold transition">Theo tuần</button>
                <button type="button" @click="chartMode = 'month'; window.foodDoanhSoChartSetMode && window.foodDoanhSoChartSetMode('month')" :class="chartMode === 'month' ? 'bg-brand-600 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 dark:text-gray-300'" class="rounded-lg px-3 py-1.5 text-xs font-semibold transition">Theo tháng</button>
            </div>
            <button type="button" onclick="document.getElementById('food-doanhso-chart-wrap')?.requestFullscreen?.()" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700" title="Toàn màn hình">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
            </button>
            <button type="button" @click="filterOpen = !filterOpen" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                Lọc
            </button>
        </div>
    </div>

    <div x-show="filterOpen" x-cloak x-transition class="border-b border-gray-100 px-4 py-3 dark:border-gray-800 sm:px-6">
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
        @if(isset($from) && isset($to))
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Kỳ: {{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }}</p>
        @endif
    </div>

    <div id="food-doanhso-chart-wrap" class="bg-white px-2 pb-4 pt-2 dark:bg-gray-900 sm:px-4">
        <div id="food-doanhso-chart"
            class="min-h-[300px] w-full"
            data-dates="{{ json_encode($chartDoanhSoDates) }}"
            data-labels="{{ json_encode($chartLabels) }}"
            data-quyettoan="{{ json_encode($chartDoanhSoQuyetToan) }}"
            data-loinhuan="{{ json_encode($chartDoanhSoLoiNhuan) }}"></div>
    </div>
</div>

<script>
(function() {
    var chartEl = document.getElementById('food-doanhso-chart');
    if (!chartEl) return;

    var rawDates = JSON.parse(chartEl.getAttribute('data-dates') || '[]');
    var rawLabels = JSON.parse(chartEl.getAttribute('data-labels') || '[]');
    var rawQuyetToan = JSON.parse(chartEl.getAttribute('data-quyettoan') || '[]');
    var rawLoiNhuan = JSON.parse(chartEl.getAttribute('data-loinhuan') || '[]');
    var chartInstance = null;

    function pad(n) { return n < 10 ? '0' + n : '' + n; }

    function aggregate(mode) {
        if (mode === 'day' || !rawDates.length) {
            return { labels: rawLabels, quyetToan: rawQuyetToan, loiNhuan: rawLoiNhuan };
        }
        var buckets = {};
        rawDates.forEach(function(dateStr, i) {
            var parts = dateStr.split('-');
            var y = parseInt(parts[0], 10), m = parseInt(parts[1], 10), d = parseInt(parts[2], 10);
            var dt = new Date(y, m - 1, d);
            var key, label;
            if (mode === 'week') {
                var day = dt.getDay() || 7;
                var monday = new Date(dt);
                monday.setDate(dt.getDate() - day + 1);
                key = monday.getFullYear() + '-' + pad(monday.getMonth() + 1) + '-' + pad(monday.getDate());
                label = pad(monday.getDate()) + '/' + pad(monday.getMonth() + 1);
            } else {
                key = y + '-' + pad(m);
                label = pad(m) + '/' + y;
            }
            if (!buckets[key]) buckets[key] = { label: label, quyetToan: 0, loiNhuan: 0 };
            buckets[key].quyetToan += rawQuyetToan[i] || 0;
            buckets[key].loiNhuan += rawLoiNhuan[i] || 0;
        });
        var keys = Object.keys(buckets).sort();
        return {
            labels: keys.map(function(k) { return buckets[k].label; }),
            quyetToan: keys.map(function(k) { return buckets[k].quyetToan; }),
            loiNhuan: keys.map(function(k) { return buckets[k].loiNhuan; }),
        };
    }

    function buildOptions(data) {
        var isDark = document.documentElement.classList.contains('dark');
        return {
            series: [
                { name: 'Quyết toán', data: data.quyetToan },
                { name: 'Lợi nhuận', data: data.loiNhuan }
            ],
            chart: {
                type: 'area',
                height: 300,
                width: '100%',
                toolbar: { show: false },
                zoom: { enabled: false },
                fontFamily: 'inherit',
            },
            colors: ['#f59e0b', '#3b82f6'],
            stroke: { curve: 'smooth', width: 2.5 },
            fill: {
                type: 'gradient',
                gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.04, stops: [0, 90, 100] },
            },
            markers: { size: 4, strokeWidth: 2, strokeColors: '#fff', hover: { size: 6 } },
            dataLabels: { enabled: false },
            grid: {
                borderColor: isDark ? '#374151' : '#e5e7eb',
                strokeDashArray: 4,
                padding: { left: 8, right: 16, top: 8, bottom: 0 },
            },
            xaxis: {
                categories: data.labels,
                tickAmount: data.labels.length > 20 ? 12 : undefined,
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
            },
            tooltip: {
                shared: true,
                intersect: false,
                y: { formatter: function(v) { return new Intl.NumberFormat('vi-VN').format(v) + ' đ'; } },
            },
        };
    }

    function renderChart(mode) {
        if (typeof window.ApexCharts === 'undefined') {
            var s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/apexcharts@3.45.0/dist/apexcharts.min.js';
            s.onload = function() { renderChart(mode); };
            document.head.appendChild(s);
            return;
        }
        var data = aggregate(mode || 'day');
        if (chartInstance) {
            chartInstance.updateOptions(buildOptions(data));
            return;
        }
        chartInstance = new window.ApexCharts(chartEl, buildOptions(data));
        chartInstance.render();
    }

    window.foodDoanhSoChartSetMode = renderChart;

    if (document.readyState === 'complete') renderChart('day');
    else window.addEventListener('load', function() { renderChart('day'); });
})();

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
