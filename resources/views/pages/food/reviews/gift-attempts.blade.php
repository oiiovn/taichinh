@extends('layouts.food')

@section('foodContent')
@php
    $resultBadge = [
        'page_open' => 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-500/40 dark:bg-blue-900/30 dark:text-blue-200',
        'success' => 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-900/30 dark:text-emerald-200',
        'already_rewarded' => 'border-red-300 bg-red-50 text-red-700 dark:border-red-500/40 dark:bg-red-900/30 dark:text-red-200',
        'expired' => 'border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-500/40 dark:bg-amber-900/30 dark:text-amber-200',
        'not_found' => 'border-gray-300 bg-gray-50 text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300',
    ];
    $stats = $dailyStats ?? ['days' => [], 'dayLabels' => [], 'rows' => [], 'series' => [], 'totals' => [], 'grandTotal' => 0, 'successRate' => null, 'uniqueIps' => 0, 'resultColors' => []];
    $resultKeys = array_keys($resultLabels);
    $chartColors = array_map(fn ($k) => $stats['resultColors'][$k] ?? '#6b7280', $resultKeys);
@endphp
<div class="space-y-4" x-data="{ openKey: null }">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Lịch sử QR nhận quà</h2>
        <a href="{{ route('food.reviews.index') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">← Đánh giá</a>
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400">Cùng một IP được gom chung. Nhấn dòng để xem chi tiết từng lần thử; ưu tiên hiển thị lần <strong class="font-medium text-emerald-700 dark:text-emerald-300">Thành công</strong> nếu có.</p>

    <form method="GET" class="grid grid-cols-1 gap-2 rounded-xl border border-gray-200 bg-gray-50 p-3 md:grid-cols-5 dark:border-gray-700 dark:bg-gray-800/50">
        <input type="text" name="q" value="{{ $q }}" placeholder="Mã đơn, FR-..., IP..." class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm md:col-span-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
        <select name="result" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            <option value="">Tất cả kết quả</option>
            @foreach($resultLabels as $key => $label)
                <option value="{{ $key }}" @selected($result === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <input type="date" name="from_date" value="{{ $fromDate }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
        <input type="date" name="to_date" value="{{ $toDate }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
        <div class="md:col-span-5">
            <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Lọc</button>
        </div>
    </form>

    @if(($stats['grandTotal'] ?? 0) > 0)
        <div class="space-y-3 rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900 md:p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Thống kê theo ngày</h3>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Theo dõi xu hướng từng loại kết quả để đánh giá chiến lược QR nhận quà.</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800/80">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tổng lượt</p>
                    <p class="mt-0.5 text-lg font-semibold tabular-nums text-gray-900 dark:text-white">{{ number_format($stats['grandTotal']) }}</p>
                </div>
                <div class="rounded-lg bg-emerald-50 px-3 py-2 dark:bg-emerald-900/20">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700/80 dark:text-emerald-300/80">Thành công</p>
                    <p class="mt-0.5 text-lg font-semibold tabular-nums text-emerald-800 dark:text-emerald-200">{{ number_format($stats['totals']['success'] ?? 0) }}</p>
                </div>
                <div class="rounded-lg bg-blue-50 px-3 py-2 dark:bg-blue-900/20">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-blue-700/80 dark:text-blue-300/80">Tỷ lệ thành công</p>
                    <p class="mt-0.5 text-lg font-semibold tabular-nums text-blue-800 dark:text-blue-200">{{ $stats['successRate'] !== null ? $stats['successRate'].'%' : '—' }}</p>
                    <p class="text-[10px] text-blue-600/80 dark:text-blue-300/70">Thành công / Tổng lượt nhập</p>
                </div>
                <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800/80">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">IP khác nhau</p>
                    <p class="mt-0.5 text-lg font-semibold tabular-nums text-gray-900 dark:text-white">{{ number_format($stats['uniqueIps'] ?? 0) }}</p>
                </div>
            </div>

            <div class="min-w-0 overflow-x-auto">
                <div id="gift-attempts-chart"
                    class="min-h-[300px] min-w-[640px] w-full"
                    data-labels="{{ json_encode($stats['dayLabels']) }}"
                    data-series="{{ json_encode($stats['series']) }}"
                    data-colors="{{ json_encode($chartColors) }}"></div>
            </div>

            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 text-xs dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800/80">
                        <tr class="text-left font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-3 py-2">Ngày</th>
                            @foreach($resultKeys as $key)
                                <th class="px-3 py-2 whitespace-nowrap">{{ $resultLabels[$key] }}</th>
                            @endforeach
                            <th class="px-3 py-2">Tổng</th>
                            <th class="px-3 py-2">Tỷ lệ TC</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                        @foreach($stats['days'] as $day)
                            @php
                                $row = $stats['rows'][$day] ?? [];
                                $total = (int) ($row['total'] ?? 0);
                                $succ = (int) ($row['success'] ?? 0);
                                $dayRate = $total > 0 ? round($succ / $total * 100, 1) : null;
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="whitespace-nowrap px-3 py-2 font-medium text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($day)->format('d/m/Y') }}</td>
                                @foreach($resultKeys as $key)
                                    @php $val = (int) ($row[$key] ?? 0); @endphp
                                    <td class="px-3 py-2 tabular-nums {{ $val > 0 ? 'font-semibold text-gray-900 dark:text-white' : 'text-gray-400' }}">{{ $val ?: '—' }}</td>
                                @endforeach
                                <td class="px-3 py-2 font-semibold tabular-nums text-gray-900 dark:text-white">{{ (int) ($row['total'] ?? 0) }}</td>
                                <td class="px-3 py-2 tabular-nums {{ $dayRate !== null && $dayRate >= 50 ? 'font-semibold text-emerald-700 dark:text-emerald-300' : 'text-gray-600 dark:text-gray-300' }}">{{ $dayRate !== null ? $dayRate.'%' : '—' }}</td>
                            </tr>
                        @endforeach
                        <tr class="bg-gray-50 font-semibold dark:bg-gray-800/80">
                            <td class="px-3 py-2 text-gray-900 dark:text-white">Tổng</td>
                            @foreach($resultKeys as $key)
                                <td class="px-3 py-2 tabular-nums text-gray-900 dark:text-white">{{ (int) ($stats['totals'][$key] ?? 0) }}</td>
                            @endforeach
                            <td class="px-3 py-2 tabular-nums text-gray-900 dark:text-white">{{ (int) ($stats['grandTotal'] ?? 0) }}</td>
                            <td class="px-3 py-2 tabular-nums text-gray-900 dark:text-white">{{ $stats['successRate'] !== null ? $stats['successRate'].'%' : '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
        function renderGiftAttemptsChart() {
            var el = document.getElementById('gift-attempts-chart');
            if (!el) return;
            if (el._chartRendered) return;
            if (typeof window.ApexCharts === 'undefined') {
                var s = document.createElement('script');
                s.src = 'https://cdn.jsdelivr.net/npm/apexcharts@3.45.0/dist/apexcharts.min.js';
                s.onload = function() { el._chartRendered = false; renderGiftAttemptsChart(); };
                document.head.appendChild(s);
                return;
            }
            el._chartRendered = true;
            var labels = JSON.parse(el.getAttribute('data-labels') || '[]');
            var seriesRaw = JSON.parse(el.getAttribute('data-series') || '[]');
            var colors = JSON.parse(el.getAttribute('data-colors') || '[]');
            var series = seriesRaw.map(function(item) {
                return { name: item.name, data: item.data };
            });
            var isDark = document.documentElement.classList.contains('dark');
            var chart = new window.ApexCharts(el, {
                series: series,
                chart: {
                    type: 'bar',
                    height: 300,
                    stacked: true,
                    toolbar: { show: false },
                    fontFamily: 'inherit',
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: labels.length > 20 ? '85%' : '60%',
                        borderRadius: 2,
                    },
                },
                colors: colors.length ? colors : ['#3b82f6', '#9ca3af', '#f59e0b', '#ef4444', '#10b981'],
                dataLabels: { enabled: false },
                stroke: { show: true, width: 1, colors: [isDark ? '#111827' : '#fff'] },
                xaxis: {
                    categories: labels,
                    tickAmount: labels.length > 24 ? 12 : undefined,
                    labels: { rotate: labels.length > 14 ? -45 : 0, style: { fontSize: '11px' } },
                },
                yaxis: {
                    labels: {
                        formatter: function(v) { return Math.round(v); },
                        style: { fontSize: '11px' },
                    },
                    title: { text: 'Số lượt', style: { fontSize: '11px' } },
                },
                legend: {
                    position: 'bottom',
                    horizontalAlign: 'center',
                    fontSize: '11px',
                    itemMargin: { horizontal: 8, vertical: 4 },
                },
                grid: {
                    borderColor: isDark ? '#374151' : '#e5e7eb',
                    strokeDashArray: 4,
                },
                tooltip: {
                    shared: true,
                    intersect: false,
                    y: { formatter: function(v) { return v + ' lượt'; } },
                },
            });
            chart.render();
        }
        if (document.readyState === 'complete') renderGiftAttemptsChart();
        else window.addEventListener('load', renderGiftAttemptsChart);
        </script>
    @endif

    <div class="space-y-2">
        @forelse($groups as $group)
            @php
                $primary = $group['primary'];
                $groupKey = $group['key'];
            @endphp
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                <button type="button"
                    @click="openKey = openKey === @js($groupKey) ? null : @js($groupKey)"
                    class="flex w-full items-start gap-3 px-3 py-3 text-left transition hover:bg-gray-50 dark:hover:bg-gray-800/60">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-400 transition-transform"
                        :class="openKey === @js($groupKey) && 'rotate-90'"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-sm font-semibold text-gray-900 dark:text-white">{{ $group['ip'] ?? 'Không rõ IP' }}</span>
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $group['count'] }} lần</span>
                            @if($group['has_success'])
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">Có thành công</span>
                            @endif
                        </div>
                        <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                            <span class="inline-flex rounded border px-2 py-0.5 font-medium {{ $resultBadge[$primary->result] ?? $resultBadge['not_found'] }}">
                                {{ $primary->resultLabel() }}
                            </span>
                            <span class="font-mono text-gray-800 dark:text-gray-200">{{ $primary->order_code_input }}</span>
                            @if($primary->gift_code)
                                <span class="font-mono font-semibold text-emerald-700 dark:text-emerald-300">{{ $primary->gift_code }}</span>
                            @endif
                            <span class="text-gray-500 dark:text-gray-400">{{ $group['latest_at']?->format('d/m/Y H:i:s') }}</span>
                            @if($primary->review?->branch?->name)
                                <span class="text-gray-500 dark:text-gray-400">· {{ $primary->review->branch->name }}</span>
                            @endif
                        </div>
                    </div>
                </button>

                <div x-show="openKey === @js($groupKey)" x-cloak class="border-t border-gray-100 dark:border-gray-800">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                            <thead class="bg-gray-50/80 dark:bg-gray-800/50">
                                <tr class="text-left text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="px-3 py-2">Thời gian</th>
                                    <th class="px-3 py-2">Mã nhập</th>
                                    <th class="px-3 py-2">Kết quả</th>
                                    <th class="px-3 py-2">Mã quà</th>
                                    <th class="px-3 py-2">Chi nhánh</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 bg-white dark:divide-gray-800/80 dark:bg-gray-900">
                                @foreach($group['attempts'] as $a)
                                    <tr class="align-top {{ $a->result === 'success' ? 'bg-emerald-50/40 dark:bg-emerald-900/10' : '' }}">
                                        <td class="whitespace-nowrap px-3 py-2 text-xs text-gray-600 dark:text-gray-300">
                                            {{ $a->created_at?->format('d/m/Y H:i:s') }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <span class="font-mono text-xs font-semibold text-gray-900 dark:text-gray-100">{{ $a->order_code_input }}</span>
                                            @if($a->result_message)
                                                <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">{{ $a->result_message }}</p>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">
                                            <span class="inline-flex rounded border px-2 py-0.5 text-[11px] font-medium {{ $resultBadge[$a->result] ?? $resultBadge['not_found'] }}">
                                                {{ $a->resultLabel() }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 font-mono text-xs text-gray-800 dark:text-gray-200">{{ $a->gift_code ?: '—' }}</td>
                                        <td class="px-3 py-2 text-xs text-gray-600 dark:text-gray-300">{{ $a->review?->branch?->name ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-3 py-8 text-center dark:border-gray-700 dark:bg-gray-900/50">
                <p class="text-sm text-gray-500 dark:text-gray-400">Chưa có lịch sử nhập mã.</p>
            </div>
        @endforelse
    </div>

    <div>{{ $groups->links() }}</div>
</div>
@endsection
