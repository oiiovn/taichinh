@php
    $analytics = $analyticsData ?? null;
    $monthly = $analytics['monthly'] ?? null;
    $daily = $analytics['daily'] ?? null;
    $byCategory = $analytics['byCategory'] ?? [];
    $categoryItems = $byCategory['items'] ?? [];
    $concentration = $byCategory['concentration'] ?? [];
    $linkedAccountNumbers = $linkedAccountNumbers ?? [];
    $hasMonthly = $monthly && !empty($monthly['monthly']);
    $monthlyList = $hasMonthly ? $monthly['monthly'] : [];
    $hasDaily = $daily && !empty($daily['daily']);
    $dailyList = $hasDaily ? $daily['daily'] : [];
    $summary = $monthly['summary'] ?? null;
    $trajectory = $monthly['trajectory'] ?? null;
    $stability = $monthly['stability'] ?? null;
    $anomalyAlerts = $monthly['anomaly_alerts'] ?? [];
    $strategySummary = $analytics['strategySummary'] ?? null;
    $healthStatus = $analytics['health_status'] ?? null;
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
        <h2 class="text-theme-xl font-semibold text-gray-900 dark:text-white">Phân tích thu chi</h2>
    </div>

    {{-- Lọc --}}
    <form method="GET" action="{{ route('tai-chinh') }}" class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
        <input type="hidden" name="tab" value="phan-tich">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-theme-sm text-gray-500 dark:text-gray-400">Lọc theo kỳ và tài khoản để xem số liệu thực tế từ giao dịch.</p>
            <div class="flex flex-wrap items-center gap-3">
                <select name="phan_tich_months" class="h-10 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:focus:border-brand-600 sm:w-[120px]">
                    @foreach([6 => '6 tháng', 12 => '12 tháng', 24 => '24 tháng'] as $v => $l)
                        <option value="{{ $v }}" {{ (int) request('phan_tich_months', 12) === $v ? 'selected' : '' }}>{{ $l }}</option>
                    @endforeach
                </select>
                <select name="phan_tich_stk" class="h-10 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:focus:border-brand-600 sm:w-[160px]">
                    <option value="">Tất cả tài khoản</option>
                    @foreach($linkedAccountNumbers as $num)
                        <option value="{{ $num }}" {{ request('phan_tich_stk') === $num ? 'selected' : '' }}>{{ $num }}</option>
                    @endforeach
                </select>
                <button type="submit" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500/10">Áp dụng</button>
            </div>
        </div>
    </form>

    @guest
        <div class="rounded-xl border border-gray-200 bg-white p-8 text-center dark:border-gray-800 dark:bg-gray-900 dark:text-white">
            <p class="text-theme-sm text-gray-500 dark:text-gray-400">Vui lòng đăng nhập để xem phân tích thu chi.</p>
        </div>
    @else
        @if(!$hasMonthly && empty($categoryItems))
            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 py-10 text-center dark:border-gray-700 dark:bg-gray-800/50 dark:text-white">
                <p class="text-theme-sm text-gray-500 dark:text-gray-400">Chưa có dữ liệu giao dịch trong kỳ đã chọn. Hãy liên kết tài khoản và đồng bộ giao dịch ở tab Tài khoản và Giao dịch.</p>
            </div>
        @else
            {{-- Trạng thái tài chính tổng hợp (đầu trang) --}}
            @if($healthStatus)
            <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                @php
                    $hsBg = $healthStatus['key'] === 'stable' ? 'bg-success-50 border-success-200 dark:bg-success-900/20 dark:border-success-800' : ($healthStatus['key'] === 'danger' ? 'bg-error-50 border-error-200 dark:bg-error-900/20 dark:border-error-800' : 'bg-warning-50 border-warning-200 dark:bg-warning-900/20 dark:border-warning-800');
                @endphp
                <div class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 {{ $hsBg }}">
                    <span class="text-xl">{{ $healthStatus['icon'] ?? '🟢' }}</span>
                    <span class="text-base font-semibold text-gray-900 dark:text-white">{{ $healthStatus['label'] ?? 'Tài chính ổn định' }}</span>
                </div>
            </div>
            @endif

            {{-- Thẻ tổng quan: Thu, Chi, Net, Burn, Thu TB, Xu hướng, Stability (+ sparkline data) --}}
            @if($summary || $trajectory)
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
                @if($summary)
                    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                        <p class="mb-1 text-theme-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Tổng thu (kỳ)</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ number_format($summary['total_thu']) }} ₫</p>
                        @if($hasMonthly && count($monthlyList) > 0)
                            <div class="mt-2 h-8 w-full" data-sparkline="{{ json_encode(array_map(fn($m) => (float)$m['thu'], $monthlyList)) }}" data-sparkline-color="#22c55e"></div>
                        @endif
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                        <p class="mb-1 text-theme-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Tổng chi (kỳ)</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ number_format($summary['total_chi']) }} ₫</p>
                        @if($hasMonthly && count($monthlyList) > 0)
                            <div class="mt-2 h-8 w-full" data-sparkline="{{ json_encode(array_map(fn($m) => (float)$m['chi'], $monthlyList)) }}" data-sparkline-color="#ef4444"></div>
                        @endif
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                        <p class="mb-1 text-theme-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Dòng tiền ròng kỳ</p>
                        @php $net = $summary['net_cashflow'] ?? 0; @endphp
                        <p class="text-lg font-semibold {{ $net >= 0 ? 'text-success-700 dark:text-success-400' : 'text-error-700 dark:text-error-400' }}">{{ $net >= 0 ? '+' : '' }}{{ number_format($net) }} ₫</p>
                        @if(isset($summary['pct_change_net']) && $summary['pct_change_net'] !== null)
                            <p class="mt-0.5 text-theme-xs {{ $summary['pct_change_net'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-error-600 dark:text-error-400' }}">{{ $summary['pct_change_net'] >= 0 ? '+' : '' }}{{ number_format($summary['pct_change_net'], 1) }}% so với kỳ trước</p>
                        @endif
                        @if($hasMonthly && count($monthlyList) > 0)
                            <div class="mt-2 h-8 w-full" data-sparkline="{{ json_encode(array_map(fn($m) => (float)$m['surplus'], $monthlyList)) }}" data-sparkline-color="{{ $net >= 0 ? '#22c55e' : '#ef4444' }}"></div>
                        @endif
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                        <p class="mb-1 text-theme-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Tỷ lệ chi / thu</p>
                        @php
                            $burn = $summary['burn_ratio'] ?? null;
                            $burnClass = $burn === null ? '' : ($burn > 100 ? 'text-error-700 dark:text-error-400' : ($burn >= 70 && $burn <= 100 ? 'text-warning-700 dark:text-warning-400' : 'text-success-700 dark:text-success-400'));
                        @endphp
                        <p class="text-lg font-semibold {{ $burnClass }}">{{ $burn !== null ? number_format($burn, 0) . '%' : '—' }}</p>
                        @if($burn !== null)
                            @if($burn > 100)
                                <p class="mt-0.5 text-theme-xs text-error-600 dark:text-error-400">Đang âm cấu trúc</p>
                            @elseif($burn >= 70 && $burn <= 100)
                                <p class="mt-0.5 text-theme-xs text-gray-500 dark:text-gray-400">Đang ổn</p>
                            @elseif($burn < 50)
                                <p class="mt-0.5 text-theme-xs text-success-600 dark:text-success-400">Dư địa đầu tư</p>
                            @endif
                        @endif
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                        <p class="mb-1 text-theme-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Thu TB / tháng</p>
                        <p class="text-lg font-semibold text-success-700 dark:text-success-400">{{ number_format($summary['avg_thu']) }} ₫</p>
                    </div>
                @endif
                @if($trajectory)
                    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                        <p class="mb-1 text-theme-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Xu hướng dư tháng</p>
                        @php
                            $dir = $trajectory['direction'] ?? 'stable';
                            $bg = $dir === 'improving' ? 'bg-success-100 text-success-800 dark:bg-success-900/40 dark:text-success-300' : ($dir === 'deteriorating' ? 'bg-error-100 text-error-800 dark:bg-error-900/40 dark:text-error-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300');
                        @endphp
                        <span class="inline-flex rounded-full px-3 py-1 text-theme-sm font-medium {{ $bg }}">{{ $trajectory['trend_label'] ?? $trajectory['label'] ?? 'Ổn định' }}</span>
                        @if(!empty($trajectory['hint']))
                            <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">{{ $trajectory['hint'] }}</p>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Stability Score --}}
            @if($stability && $stability['label'] !== null)
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                <h3 class="mb-2 text-base font-semibold text-gray-800 dark:text-white">Ổn định dòng tiền</h3>
                <div class="flex flex-wrap items-center gap-4">
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-theme-sm font-medium {{ $stability['score'] >= 70 ? 'bg-success-100 text-success-800 dark:bg-success-900/40 dark:text-success-300' : ($stability['score'] >= 40 ? 'bg-warning-100 text-warning-800 dark:bg-warning-900/40 dark:text-warning-300' : 'bg-error-100 text-error-800 dark:bg-error-900/40 dark:text-error-300') }}">{{ $stability['label'] }}</span>
                    @if($stability['score'] !== null)
                        <span class="text-theme-sm text-gray-500 dark:text-gray-400">Điểm ổn định: {{ $stability['score'] }}/100</span>
                    @endif
                    @if(isset($stability['cv_thu']) || isset($stability['cv_chi']))
                        <span class="text-theme-xs text-gray-500 dark:text-gray-400">Hệ số biến thiên thu: {{ $stability['cv_thu'] ?? '—' }}% · chi: {{ $stability['cv_chi'] ?? '—' }}%</span>
                    @endif
                </div>
            </div>
            @endif

            {{-- Cảnh báo bất thường --}}
            @if(!empty($anomalyAlerts))
            <div class="rounded-xl border border-warning-200 bg-warning-50 p-5 dark:border-warning-800 dark:bg-warning-900/20 dark:text-white">
                <h3 class="mb-2 text-base font-semibold text-warning-800 dark:text-warning-300">Cảnh báo bất thường</h3>
                <ul class="list-inside list-disc space-y-1 text-theme-sm text-warning-800 dark:text-warning-200">
                    @foreach($anomalyAlerts as $alert)
                        <li>{{ $alert['message'] ?? '' }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
            @endif

            {{-- Biểu đồ: cột Thu/Chi + đường Net overlay (theo tháng hoặc theo ngày) --}}
            @if($hasMonthly || $hasDaily)
            <div class="rounded-2xl border border-gray-200 bg-white px-5 pb-5 pt-5 dark:border-gray-800 dark:bg-white/[0.03] sm:px-6 sm:pt-6" x-data="{ chartMode: '{{ $hasMonthly ? 'month' : 'day' }}' }">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Thu, chi và dòng tiền ròng</h3>
                    </div>
                    @if($hasMonthly && $hasDaily)
                    <div class="flex rounded-lg border border-gray-200 bg-gray-50 p-0.5 dark:border-gray-700 dark:bg-gray-800/50">
                        <button type="button" @click="chartMode = 'month'; window.switchPhanTichChart && window.switchPhanTichChart('month')" :class="chartMode === 'month' ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white' : 'text-gray-600 dark:text-gray-400'" class="rounded-md px-3 py-1.5 text-theme-sm font-medium transition-colors">Theo tháng</button>
                        <button type="button" @click="chartMode = 'day'; window.switchPhanTichChart && window.switchPhanTichChart('day')" :class="chartMode === 'day' ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white' : 'text-gray-600 dark:text-gray-400'" class="rounded-md px-3 py-1.5 text-theme-sm font-medium transition-colors">Theo ngày</button>
                    </div>
                    @endif
                </div>
                <div class="max-w-full overflow-x-auto custom-scrollbar">
                    <div id="chartPhanTichThuChi" class="-ml-4 min-h-[300px] min-w-[600px] pl-2 xl:min-w-full" data-chart-ready="0"></div>
                </div>
                <script type="application/json" id="chartPhanTichThuChiData">{!! json_encode([
                    'month' => [
                        'categories' => array_column($monthlyList, 'month_label'),
                        'thu' => array_map(fn($m) => (float) $m['thu'], $monthlyList),
                        'chi' => array_map(fn($m) => (float) $m['chi'], $monthlyList),
                        'net' => array_map(fn($m) => (float) $m['surplus'], $monthlyList),
                    ],
                    'day' => [
                        'categories' => array_column($dailyList, 'date_label'),
                        'thu' => array_map(fn($d) => (float) $d['thu'], $dailyList),
                        'chi' => array_map(fn($d) => (float) $d['chi'], $dailyList),
                        'net' => array_map(fn($d) => (float) $d['surplus'], $dailyList),
                    ],
                ]) !!}</script>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var el = document.getElementById('chartPhanTichThuChi');
                        var dataEl = document.getElementById('chartPhanTichThuChiData');
                        if (!el || !dataEl || el.getAttribute('data-chart-ready') === '1') return;
                        var all = JSON.parse(dataEl.textContent);
                        var hasMonth = all.month && all.month.categories && all.month.categories.length > 0;
                        var hasDay = all.day && all.day.categories && all.day.categories.length > 0;
                        if (!hasMonth && !hasDay) return;
                        el.setAttribute('data-chart-ready', '1');
                        if (typeof window.ApexCharts === 'undefined') return;
                        var data = hasMonth ? all.month : all.day;
                        var opts = {
                            series: [
                                { name: 'Thu', type: 'column', data: data.thu || [] },
                                { name: 'Chi', type: 'column', data: data.chi || [] },
                                { name: 'Dòng tiền ròng', type: 'line', data: data.net || [] }
                            ],
                            colors: ['#22c55e', '#ef4444', '#3b82f6'],
                            chart: { fontFamily: 'Outfit, sans-serif', type: 'line', height: 320, toolbar: { show: false } },
                            stroke: { width: [0, 0, 3], curve: 'smooth' },
                            plotOptions: { bar: { columnWidth: '55%', borderRadius: 4, borderRadiusApplication: 'end' } },
                            dataLabels: { enabled: false },
                            xaxis: { categories: data.categories, axisBorder: { show: false }, axisTicks: { show: false } },
                            legend: { show: true, position: 'top', horizontalAlign: 'right' },
                            yaxis: { title: false, labels: { formatter: function(v) { return new Intl.NumberFormat('vi-VN', { notation: 'compact', maximumFractionDigits: 1 }).format(v); } } },
                            grid: { yaxis: { lines: { show: true } } },
                            fill: { opacity: 1 },
                            tooltip: { y: { formatter: function(v) { return new Intl.NumberFormat('vi-VN').format(v) + ' ₫'; } } }
                        };
                        var chart = new window.ApexCharts(el, opts);
                        chart.render();
                        window.chartPhanTichThuChiInstance = chart;
                        window.switchPhanTichChart = function(mode) {
                            var d = (mode === 'day' && hasDay) ? all.day : (hasMonth ? all.month : all.day);
                            if (!d || !d.categories) return;
                            chart.updateOptions({ xaxis: { categories: d.categories } });
                            chart.updateSeries([
                                { name: 'Thu', type: 'column', data: d.thu || [] },
                                { name: 'Chi', type: 'column', data: d.chi || [] },
                                { name: 'Dòng tiền ròng', type: 'line', data: d.net || [] }
                            ]);
                        };
                    });
                </script>
            </div>
            @endif

            {{-- Phân bổ chi theo danh mục + Tập trung rủi ro --}}
            @if(!empty($categoryItems))
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Phân bổ chi theo danh mục</h3>
                        <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">Top danh mục chi tiêu trong kỳ (từ giao dịch đã phân loại).</p>
                    </div>
                    @if(!empty($concentration))
                        @php
                            $concBg = ($concentration['top1_pct'] ?? 0) >= 75 ? 'bg-error-100 text-error-800 dark:bg-error-900/40 dark:text-error-300' : (($concentration['top1_pct'] ?? 0) >= 50 ? 'bg-warning-100 text-warning-800 dark:bg-warning-900/40 dark:text-warning-300' : 'bg-success-100 text-success-800 dark:bg-success-900/40 dark:text-success-300');
                        @endphp
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800/50">
                            <p class="text-theme-xs font-medium text-gray-500 dark:text-gray-400">Tập trung rủi ro</p>
                            <p class="text-theme-sm font-semibold text-gray-900 dark:text-white">Top 1: {{ $concentration['top1_pct'] ?? 0 }}% · HHI: {{ $concentration['hhi'] ?? 0 }}</p>
                            <span class="inline-flex mt-1 rounded-full px-2 py-0.5 text-theme-xs font-medium {{ $concBg }}">{{ $concentration['label'] ?? '' }}</span>
                        </div>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-theme-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="pb-3 font-medium text-gray-800 dark:text-white">Danh mục</th>
                                <th class="pb-3 font-medium text-gray-800 dark:text-white text-right">Số giao dịch</th>
                                <th class="pb-3 font-medium text-gray-800 dark:text-white text-right">Tổng (₫)</th>
                                <th class="pb-3 font-medium text-gray-800 dark:text-white text-right w-24">Tỷ trọng</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($categoryItems as $row)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-3 text-gray-800 dark:text-gray-200">{{ $row['name'] }}</td>
                                <td class="py-3 text-right text-gray-600 dark:text-gray-400">{{ number_format($row['count']) }}</td>
                                <td class="py-3 text-right font-medium text-gray-900 dark:text-white">{{ number_format($row['total']) }}</td>
                                <td class="py-3 text-right">
                                    <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-theme-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300">{{ $row['pct'] }}%</span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- Block Insight: Thu TB, Chi TB, Net TB, Burn + text --}}
            @if($strategySummary)
            <div class="rounded-xl border border-gray-200 bg-gray-50/80 p-5 dark:border-gray-700 dark:bg-gray-800/50 dark:text-white">
                <p class="mb-3 text-theme-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Số liệu dùng cho Insight</p>
                <p class="mb-4 text-theme-sm text-gray-600 dark:text-gray-300">Insight đang sử dụng các số liệu sau (từ phân tích thực tế {{ $strategySummary['months'] ?? 12 }} tháng) để dự báo và đề xuất.</p>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div>
                        <p class="text-theme-xs text-gray-500 dark:text-gray-400">Thu TB {{ $strategySummary['months'] ?? 12 }} tháng</p>
                        <p class="text-base font-semibold text-gray-900 dark:text-white">{{ number_format($strategySummary['avg_thu']) }} ₫</p>
                    </div>
                    <div>
                        <p class="text-theme-xs text-gray-500 dark:text-gray-400">Chi TB {{ $strategySummary['months'] ?? 12 }} tháng</p>
                        <p class="text-base font-semibold text-gray-900 dark:text-white">{{ number_format($strategySummary['avg_chi']) }} ₫</p>
                    </div>
                    <div>
                        <p class="text-theme-xs text-gray-500 dark:text-gray-400">Net TB</p>
                        <p class="text-base font-semibold {{ ($strategySummary['net_avg'] ?? 0) >= 0 ? 'text-success-700 dark:text-success-400' : 'text-error-700 dark:text-error-400' }}">{{ number_format($strategySummary['net_avg']) }} ₫</p>
                    </div>
                    <div>
                        <p class="text-theme-xs text-gray-500 dark:text-gray-400">Tỷ lệ chi/thu</p>
                        <p class="text-base font-semibold text-gray-900 dark:text-white">{{ $strategySummary['burn_ratio'] !== null ? number_format($strategySummary['burn_ratio'], 1) . '%' : '—' }}</p>
                    </div>
                </div>
                <p class="mt-4 text-theme-sm text-gray-500 dark:text-gray-400">Xem dự báo và đề xuất tại tab <a href="{{ route('tai-chinh', ['tab' => 'chien-luoc']) }}" class="font-medium text-brand-600 hover:underline dark:text-brand-400">Insight</a>.</p>
            </div>
            @elseif($projection['sources'] ?? null)
            <div class="rounded-xl border border-gray-200 bg-gray-50/80 p-5 dark:border-gray-700 dark:bg-gray-800/50 dark:text-white">
                <p class="mb-2 text-theme-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Số liệu dùng cho Insight</p>
                <p class="text-theme-sm text-gray-600 dark:text-gray-300">Thu dự kiến và chi tiêu trung bình mà engine dùng để dự báo dòng tiền nằm ở tab <a href="{{ route('tai-chinh', ['tab' => 'chien-luoc']) }}" class="font-medium text-brand-600 hover:underline dark:text-brand-400">Insight</a>.</p>
            </div>
            @endif
        @endif
    @endguest
</div>

{{-- Sparklines init (mini chart trong thẻ) --}}
@if($hasMonthly && !empty($monthlyList))
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.ApexCharts === 'undefined') return;
    document.querySelectorAll('[data-sparkline]').forEach(function(div) {
        var raw = div.getAttribute('data-sparkline');
        var color = div.getAttribute('data-sparkline-color') || '#465fff';
        if (!raw) return;
        try {
            var arr = JSON.parse(raw);
            if (!arr || arr.length === 0) return;
            div.innerHTML = '';
            var chart = new window.ApexCharts(div, {
                series: [{ name: '', data: arr }],
                chart: { type: 'area', height: 32, sparkline: { enabled: true }, animations: { enabled: false } },
                stroke: { curve: 'smooth', width: 1.5 },
                fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0 } },
                colors: [color],
                tooltip: { fixed: { enabled: false }, y: { formatter: function(v) { return new Intl.NumberFormat('vi-VN').format(v) + ' ₫'; } } }
            });
            chart.render();
        } catch (e) {}
    });
});
</script>
@endif
