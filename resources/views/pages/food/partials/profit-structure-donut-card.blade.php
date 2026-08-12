@php
    $profitByBranch = $profitByBranch ?? collect();
    $totalLoiNhuan = (float) $profitByBranch->sum('loi_nhuan');
    $donutColors = ['#10b981', '#f59e0b', '#ef4444', '#3b82f6', '#8b5cf6', '#ec4899'];
    $donutSeries = [];
    $donutLabels = [];
    $donutPercents = [];
    foreach ($profitByBranch as $i => $row) {
        $val = (float) ($row['loi_nhuan'] ?? 0);
        $donutSeries[] = abs($val) < 0.01 ? 0.01 : abs($val);
        $donutLabels[] = $row['branch_name'];
        $donutPercents[] = $totalLoiNhuan != 0 ? round($val / $totalLoiNhuan * 100, 1) : 0;
    }
@endphp
<div class="flex h-full flex-col overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
    <div class="border-b border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-5">
        <h3 class="text-sm font-bold text-gray-900 dark:text-white">Cơ cấu lợi nhuận theo chi nhánh</h3>
    </div>

    @if($profitByBranch->isNotEmpty() && $totalLoiNhuan != 0)
        <div class="flex flex-1 flex-col px-2 py-3 sm:px-4">
            <div id="food-profit-donut-chart"
                class="mx-auto min-h-[220px] w-full max-w-[280px]"
                data-series="{{ json_encode($donutSeries) }}"
                data-labels="{{ json_encode($donutLabels) }}"
                data-percents="{{ json_encode($donutPercents) }}"
                data-colors="{{ json_encode(array_slice($donutColors, 0, count($donutSeries))) }}"
                data-total="{{ $totalLoiNhuan }}"
                data-total-fmt="{{ $fmt($totalLoiNhuan) }}"></div>

            <ul class="mt-3 space-y-2 px-2">
                @foreach($profitByBranch as $i => $row)
                    @php
                        $pct = $totalLoiNhuan != 0 ? round(($row['loi_nhuan'] ?? 0) / $totalLoiNhuan * 100, 1) : 0;
                        $color = $donutColors[$i % count($donutColors)];
                    @endphp
                    <li class="flex items-center justify-between gap-2 text-sm">
                        <span class="flex min-w-0 items-center gap-2 text-gray-700 dark:text-gray-300">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $color }}"></span>
                            <span class="truncate">{{ $row['branch_name'] }}</span>
                        </span>
                        <span @class([
                            'shrink-0 font-semibold tabular-nums',
                            'text-emerald-600 dark:text-emerald-400' => $pct >= 0,
                            'text-rose-600 dark:text-rose-400' => $pct < 0,
                        ])>{{ $pct }}%</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="border-t border-gray-100 px-4 py-3 dark:border-gray-800 sm:px-5">
            <a href="{{ route('food.bao-cao-ban-hang') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400">
                Xem chi tiết báo cáo
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        <script>
        (function renderProfitDonut() {
            var el = document.getElementById('food-profit-donut-chart');
            if (!el || el._rendered) return;
            function draw() {
                if (typeof window.ApexCharts === 'undefined') return false;
                el._rendered = true;
                var series = JSON.parse(el.getAttribute('data-series') || '[]');
                var labels = JSON.parse(el.getAttribute('data-labels') || '[]');
                var colors = JSON.parse(el.getAttribute('data-colors') || '[]');
                var totalFmt = el.getAttribute('data-total-fmt') || '0';
                new window.ApexCharts(el, {
                    series: series,
                    labels: labels,
                    chart: { type: 'donut', height: 240, fontFamily: 'inherit' },
                    colors: colors,
                    stroke: { width: 2, colors: ['#fff'] },
                    plotOptions: {
                        pie: {
                            donut: {
                                size: '72%',
                                labels: {
                                    show: true,
                                    name: { show: true, fontSize: '11px', color: '#6b7280' },
                                    value: { show: false },
                                    total: {
                                        show: true,
                                        label: 'Tổng lợi nhuận',
                                        fontSize: '11px',
                                        color: '#6b7280',
                                        formatter: function() { return totalFmt + ' đ'; },
                                    },
                                },
                            },
                        },
                    },
                    dataLabels: { enabled: false },
                    legend: { show: false },
                    tooltip: {
                        y: { formatter: function(v) { return new Intl.NumberFormat('vi-VN').format(v) + ' đ'; } },
                    },
                }).render();
                return true;
            }
            if (draw()) return;
            var tries = 0;
            var timer = setInterval(function() {
                tries++;
                if (draw() || tries >= 40) clearInterval(timer);
            }, 120);
        })();
        </script>
    @else
        <div class="flex flex-1 items-center justify-center px-4 py-10">
            <p class="text-center text-sm text-gray-500 dark:text-gray-400">Không có dữ liệu cơ cấu lợi nhuận.</p>
        </div>
    @endif
</div>
