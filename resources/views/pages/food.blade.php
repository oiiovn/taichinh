@extends('layouts.food')

@section('foodContent')
@php
    $validTabs = ['tong-quan', 'doanh-so'];
    $tab = $tab ?? (in_array(request('tab'), $validTabs) ? request('tab') : 'tong-quan');
    $fmt = fn ($n) => \App\Helpers\BaoCaoHelper::formatGiaVonNguyen($n);
    $periods = [
        'ngay' => 'Hôm nay',
        'tuan' => 'Tuần',
        'thang' => 'Tháng này',
        '3-thang' => '3 tháng',
        '6-thang' => '6 tháng',
        '12-thang' => '12 tháng',
    ];
    $danhMucThu = $danhMucThu ?? collect();
    $danhMucChi = $danhMucChi ?? collect();
    $period = $period ?? 'thang';
    $thuCategoryIds = $thuCategoryIds ?? [];
    $chiCategoryIds = $chiCategoryIds ?? [];
    $thuTotal = (float) ($thuTotal ?? 0);
    $chiTotal = (float) ($chiTotal ?? 0);
    $loiNhuan = (float) ($loiNhuan ?? 0);
    $fromDateInput = $fromDateInput ?? '';
    $toDateInput = $toDateInput ?? '';
@endphp
@if($tab === 'tong-quan')
    <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Tổng quan</h2>

    {{-- Bộ lọc thời gian + từ ngày đến ngày + nút mở form Tạo thống kê --}}
    <div class="mb-4" x-data="{ open: false }">
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Bộ lọc:</span>
            @foreach($periods as $val => $label)
                <a href="{{ route('food', ['period' => $val]) }}" class="rounded-lg border px-3 py-1.5 text-sm {{ $period === $val ? 'border-brand-500 bg-brand-50 text-brand-600 dark:bg-brand-500/20 dark:text-brand-400' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">{{ $label }}</a>
            @endforeach
            <form method="get" action="{{ route('food') }}" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="period" value="{{ $period }}">
                <input type="text" id="food-from-date" name="from_date" value="{{ $fromDateInput }}" placeholder="Từ ngày" readonly class="relative z-10 w-[90px] min-h-[38px] cursor-pointer rounded-lg border border-gray-200 bg-white p-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" autocomplete="off">
                <input type="text" id="food-to-date" name="to_date" value="{{ $toDateInput }}" placeholder="Đến ngày" readonly class="relative z-10 w-[90px] min-h-[38px] cursor-pointer rounded-lg border border-gray-200 bg-white p-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" autocomplete="off">
                <button type="submit" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Áp dụng</button>
            </form>
            <button type="button" @click="open = !open" class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-lg leading-none text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700" :aria-expanded="open" :title="open ? 'Thu gọn' : 'Tạo thống kê'">
                <span x-show="!open">+</span>
                <span x-show="open" x-cloak style="display: none;">−</span>
            </button>
        </div>

        {{-- Chọn danh mục rồi nhấn nút Tạo thống kê — ẩn mặc định, mở khi bấm + --}}
        <div x-show="open" x-cloak x-transition class="mb-6 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50" style="display: none;">
        <form method="get" action="{{ route('food') }}" class="flex flex-wrap gap-6">
            <input type="hidden" name="period" value="{{ $period }}">
            @if($fromDateInput)<input type="hidden" name="from_date" value="{{ $fromDateInput }}">@endif
            @if($toDateInput)<input type="hidden" name="to_date" value="{{ $toDateInput }}">@endif
            <div class="min-w-[220px]">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Danh mục thu</label>
                <div class="max-h-40 overflow-y-auto rounded-lg border border-gray-200 bg-white p-2 dark:border-gray-600 dark:bg-gray-800">
                    @foreach($danhMucThu as $c)
                        <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 hover:bg-gray-100 dark:hover:bg-gray-700">
                            <input type="checkbox" name="thu_category_ids[]" value="{{ $c->id }}" {{ in_array($c->id, $thuCategoryIds) ? 'checked' : '' }} class="rounded border-gray-300">
                            <span class="text-sm text-gray-900 dark:text-white">{{ $c->name }}</span>
                        </label>
                    @endforeach
                    @if($danhMucThu->isEmpty())
                        <p class="text-xs text-gray-500 dark:text-gray-400">Chưa có danh mục thu.</p>
                    @endif
                </div>
            </div>
            <div class="min-w-[220px]">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Danh mục chi</label>
                <div class="max-h-40 overflow-y-auto rounded-lg border border-gray-200 bg-white p-2 dark:border-gray-600 dark:bg-gray-800">
                    @foreach($danhMucChi as $c)
                        <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 hover:bg-gray-100 dark:hover:bg-gray-700">
                            <input type="checkbox" name="chi_category_ids[]" value="{{ $c->id }}" {{ in_array($c->id, $chiCategoryIds) ? 'checked' : '' }} class="rounded border-gray-300">
                            <span class="text-sm text-gray-900 dark:text-white">{{ $c->name }}</span>
                        </label>
                    @endforeach
                    @if($danhMucChi->isEmpty())
                        <p class="text-xs text-gray-500 dark:text-gray-400">Chưa có danh mục chi.</p>
                    @endif
                </div>
            </div>
            <div class="flex items-end">
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Tạo thống kê</button>
            </div>
        </form>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Thu</p>
            <p class="mt-1 text-xl font-semibold text-green-600 dark:text-green-400">{{ $fmt($thuTotal) }} đ</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Chi</p>
            <p class="mt-1 text-xl font-semibold text-red-600 dark:text-red-400">{{ $fmt($chiTotal) }} đ</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Lợi nhuận</p>
            <p class="mt-1 text-xl font-semibold {{ $loiNhuan >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">{{ $fmt($loiNhuan) }} đ</p>
        </div>
    </div>

    @if(isset($from) && isset($to))
        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Kỳ: {{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }}</p>
    @endif

    {{-- Biểu đồ đường theo ngày --}}
    @php
        $chartDates = $chartDates ?? [];
        $chartThu = $chartThu ?? [];
        $chartChi = $chartChi ?? [];
        $chartLoiNhuan = $chartLoiNhuan ?? [];
    @endphp
    <div class="mt-8 rounded-xl border border-gray-200 bg-white p-4 pr-8 dark:border-gray-700 dark:bg-gray-800">
        <p class="mb-4 text-sm font-medium text-gray-700 dark:text-gray-300">Biểu đồ theo ngày</p>
        <div class="min-w-0">
            <div id="food-tongquan-chart" class="min-h-[280px] w-full" data-dates="{{ json_encode($chartDates) }}" data-thu="{{ json_encode($chartThu) }}" data-chi="{{ json_encode($chartChi) }}" data-loinhuan="{{ json_encode($chartLoiNhuan) }}"></div>
        </div>
    </div>
    <script>
    function renderFoodChart() {
        var el = document.getElementById('food-tongquan-chart');
        if (!el) return;
        if (el._chartRendered) return;
        if (typeof window.ApexCharts === 'undefined') {
            var s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/apexcharts@3.45.0/dist/apexcharts.min.js';
            s.onload = function() { el._chartRendered = false; renderFoodChart(); };
            document.head.appendChild(s);
            return;
        }
        el._chartRendered = true;
        var dates = JSON.parse(el.getAttribute('data-dates') || '[]');
        var thu = JSON.parse(el.getAttribute('data-thu') || '[]');
        var chi = JSON.parse(el.getAttribute('data-chi') || '[]');
        var loiNhuan = JSON.parse(el.getAttribute('data-loinhuan') || '[]');
        var chart = new window.ApexCharts(el, {
            series: [
                { name: 'Thu', data: thu },
                { name: 'Chi', data: chi },
                { name: 'Lợi nhuận', data: loiNhuan }
            ],
            chart: { type: 'line', height: 280, width: '100%', toolbar: { show: false }, zoom: { enabled: false } },
            grid: { padding: { left: 24, right: 56, top: 16, bottom: 16 } },
            stroke: { curve: 'smooth', width: 2 },
            colors: ['#22c55e', '#ef4444', '#3b82f6'],
            xaxis: { categories: dates, tickAmount: dates.length > 20 ? 12 : undefined, labels: { maxHeight: 40, rotate: -45 } },
            yaxis: { labels: { formatter: function(v) { return new Intl.NumberFormat('vi-VN').format(v) + ' đ'; } } },
            legend: { position: 'bottom', horizontalAlign: 'center' },
            tooltip: { y: { formatter: function(v) { return new Intl.NumberFormat('vi-VN').format(v) + ' đ'; } } },
            dataLabels: { enabled: false }
        });
        chart.render();
    }
    if (document.readyState === 'complete') renderFoodChart();
    else window.addEventListener('load', renderFoodChart);

    (function initFoodDatePickers() {
        function run() {
            if (typeof window.flatpickr === 'undefined') return;
            var fromEl = document.getElementById('food-from-date');
            var toEl = document.getElementById('food-to-date');
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
@elseif($tab === 'doanh-so')
    <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Doanh số</h2>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Bộ lọc:</span>
        @foreach($periods as $val => $label)
            <a href="{{ route('food', ['tab' => 'doanh-so', 'period' => $val]) }}" class="rounded-lg border px-3 py-1.5 text-sm {{ $period === $val ? 'border-brand-500 bg-brand-50 text-brand-600 dark:bg-brand-500/20 dark:text-brand-400' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">{{ $label }}</a>
        @endforeach
        <form method="get" action="{{ route('food') }}" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="tab" value="doanh-so">
            <input type="hidden" name="period" value="{{ $period }}">
            <input type="text" id="food-doanhso-from-date" name="from_date" value="{{ $fromDateInput ?? '' }}" placeholder="Từ ngày" readonly class="relative z-10 w-[90px] min-h-[38px] cursor-pointer rounded-lg border border-gray-200 bg-white p-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" autocomplete="off">
            <input type="text" id="food-doanhso-to-date" name="to_date" value="{{ $toDateInput ?? '' }}" placeholder="Đến ngày" readonly class="relative z-10 w-[90px] min-h-[38px] cursor-pointer rounded-lg border border-gray-200 bg-white p-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" autocomplete="off">
            <button type="submit" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Áp dụng</button>
        </form>
    </div>

    @if(isset($from) && isset($to))
        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">Kỳ: {{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }}</p>
    @endif

    @php
        $chartDoanhSoDates = $chartDoanhSoDates ?? [];
        $chartDoanhSoLoiNhuan = $chartDoanhSoLoiNhuan ?? [];
    @endphp
    <div class="mb-8 rounded-xl border border-gray-200 bg-white p-4 pr-8 dark:border-gray-700 dark:bg-gray-800">
        <p class="mb-4 text-sm font-medium text-gray-700 dark:text-gray-300">Lợi nhuận theo ngày</p>
        <div id="food-doanhso-chart" class="min-h-[280px] w-full" data-dates="{{ json_encode($chartDoanhSoDates) }}" data-loinhuan="{{ json_encode($chartDoanhSoLoiNhuan) }}"></div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full min-w-[640px] text-left text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3 font-medium text-gray-900 dark:text-white">Mã báo cáo</th>
                    <th class="px-4 py-3 font-medium text-gray-900 dark:text-white">Ngày báo cáo</th>
                    <th class="px-4 py-3 font-medium text-gray-900 dark:text-white">Quyết toán</th>
                    <th class="px-4 py-3 font-medium text-gray-900 dark:text-white">Doanh số</th>
                    <th class="px-4 py-3 font-medium text-gray-900 dark:text-white">Lợi nhuận</th>
                    <th class="px-4 py-3 font-medium text-gray-900 dark:text-white">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reportsDoanhSo ?? [] as $r)
                    <tr class="border-b border-gray-200 dark:border-gray-700" x-data="{
                        doanhSo: {{ json_encode($r->doanh_so !== null ? (int)$r->doanh_so : '') }},
                        quyetToan: {{ (int) round($r->quyet_toan) }},
                        get loiNhuan() { var ds = parseInt(this.doanhSo, 10); if (isNaN(ds)) return null; return ds - this.quyetToan; },
                        saving: false,
                        async save() {
                            this.saving = true;
                            try {
                                const res = await fetch('{{ url('/food/bao-cao-ban-hang/' . (int) $r->id . '/doanh-so') }}', {
                                    method: 'PUT',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json' },
                                    body: JSON.stringify({ doanh_so: this.doanhSo === '' ? null : parseInt(this.doanhSo, 10) })
                                });
                                const data = await res.json();
                                if (data.success) { this.doanhSo = data.doanh_so ?? ''; }
                                else { alert(data.message || 'Lưu thất bại'); }
                            } catch (e) { alert('Lỗi kết nối'); }
                            this.saving = false;
                        }
                    }">
                        <td class="px-4 py-3 text-gray-900 dark:text-white">{{ $r->report_code ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-900 dark:text-white">{{ $r->report_date ? $r->report_date->format('d/m/Y') : '—' }}</td>
                        <td class="px-4 py-3 text-gray-900 dark:text-white">{{ $fmt($r->quyet_toan) }} đ</td>
                        <td class="px-4 py-3">
                            <input type="text" x-model="doanhSo" inputmode="numeric" placeholder="Nhập doanh số" class="w-full min-w-[100px] rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        </td>
                        <td class="px-4 py-3" x-text="loiNhuan !== null ? new Intl.NumberFormat('vi-VN').format(loiNhuan) + ' đ' : '—'"></td>
                        <td class="px-4 py-3">
                            <button type="button" @click="save()" :disabled="saving" class="rounded-lg bg-brand-600 px-3 py-1.5 text-sm text-white hover:bg-brand-700 disabled:opacity-50">Lưu</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Chưa có báo cáo nào.</td></tr>
                @endforelse
            </tbody>
        </table>
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
        var dates = JSON.parse(el.getAttribute('data-dates') || '[]');
        var loiNhuan = JSON.parse(el.getAttribute('data-loinhuan') || '[]');
        new window.ApexCharts(el, {
            series: [{ name: 'Lợi nhuận', data: loiNhuan }],
            chart: { type: 'line', height: 280, width: '100%', toolbar: { show: false }, zoom: { enabled: false } },
            grid: { padding: { left: 24, right: 56, top: 16, bottom: 16 } },
            stroke: { curve: 'smooth', width: 2 },
            colors: ['#3b82f6'],
            xaxis: { categories: dates, tickAmount: dates.length > 20 ? 12 : undefined, labels: { maxHeight: 40, rotate: -45 } },
            yaxis: { labels: { formatter: function(v) { return new Intl.NumberFormat('vi-VN').format(v) + ' đ'; } } },
            legend: { position: 'bottom', horizontalAlign: 'center' },
            tooltip: { y: { formatter: function(v) { return new Intl.NumberFormat('vi-VN').format(v) + ' đ'; } } },
            dataLabels: { enabled: false }
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
@else
    <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Tổng quan</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400">Chọn tab ở menu bên trái.</p>
@endif
@endsection
