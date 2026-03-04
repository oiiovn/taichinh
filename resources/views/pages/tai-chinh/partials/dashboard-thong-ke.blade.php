@php
    $tongquanStatistics = $tongquanStatistics ?? [];
    $danhMucThu = $danhMucThu ?? collect();
    $danhMucChi = $danhMucChi ?? collect();
    $editStat = $editTongquanStatistic ?? null;
    $fmt = fn ($n) => \App\Helpers\BaoCaoHelper::formatGiaVonNguyen($n);
    $periods = [
        'ngay' => 'Hôm nay',
        'tuan' => 'Tuần',
        'thang' => 'Tháng này',
        '3-thang' => '3 tháng',
        '6-thang' => '6 tháng',
        '12-thang' => '12 tháng',
    ];
    $formPeriod = $tongquanFormPeriod ?? 'thang';
    $formFromDate = $tongquanFormFromDate ?? '';
    $formToDate = $tongquanFormToDate ?? '';
@endphp
<div class="mt-8">
    <h3 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">Thống kê tổng quan</h3>

    {{-- Bộ lọc thời gian + từ ngày đến ngày + nút mở form Tạo thống kê — giống Food --}}
    <div class="mb-4" x-data="{ open: false }">
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Bộ lọc:</span>
            @foreach($periods as $val => $label)
                <a href="{{ route('tai-chinh', ['tab' => 'dashboard', 'period' => $val]) }}" class="rounded-lg border px-3 py-1.5 text-sm {{ $formPeriod === $val ? 'border-brand-500 bg-brand-50 text-brand-600 dark:bg-brand-500/20 dark:text-brand-400' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">{{ $label }}</a>
            @endforeach
            <form method="get" action="{{ route('tai-chinh', ['tab' => 'dashboard']) }}" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="tab" value="dashboard">
                <input type="hidden" name="period" value="{{ $formPeriod }}">
                <input type="text" id="tc-from-date" name="from_date" value="{{ $formFromDate }}" placeholder="Từ ngày" readonly class="relative z-10 w-[90px] min-h-[38px] cursor-pointer rounded-lg border border-gray-200 bg-white p-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" autocomplete="off">
                <input type="text" id="tc-to-date" name="to_date" value="{{ $formToDate }}" placeholder="Đến ngày" readonly class="relative z-10 w-[90px] min-h-[38px] cursor-pointer rounded-lg border border-gray-200 bg-white p-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" autocomplete="off">
                <button type="submit" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Áp dụng</button>
            </form>
            <button type="button" @click="open = !open" class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-lg leading-none text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700" :aria-expanded="open" :title="open ? 'Thu gọn' : 'Tạo thống kê'">
                <span x-show="!open">+</span>
                <span x-show="open" x-cloak style="display: none;">−</span>
            </button>
        </div>

        {{-- Thêm thống kê: chỉ tên + danh mục. Kỳ / từ–đến ngày lấy theo bộ lọc (mặc định tháng này). --}}
        <div x-show="open" x-cloak x-transition class="mb-6 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50" style="display: none;">
                <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Kỳ và khoảng ngày theo bộ lọc phía trên (mặc định: tháng này).</p>
                <form method="post" action="{{ route('tai-chinh.tongquan-statistic.store') }}" class="flex flex-wrap gap-6">
                    @csrf
                    <input type="hidden" name="period" value="{{ $formPeriod }}">
                    <input type="hidden" name="from_date" value="{{ $formFromDate }}">
                    <input type="hidden" name="to_date" value="{{ $formToDate }}">
                    <div class="min-w-[200px]">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Tên thống kê</label>
                        <input type="text" name="name" value="{{ old('name') }}" required maxlength="255" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="VD: Thu chi tháng 3">
                    </div>
                    <div class="min-w-[220px]">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Danh mục thu</label>
                        <div class="max-h-40 overflow-y-auto rounded-lg border border-gray-200 bg-white p-2 dark:border-gray-600 dark:bg-gray-800">
                            @foreach($danhMucThu as $c)
                                <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <input type="checkbox" name="thu_category_ids[]" value="{{ $c->id }}" {{ in_array($c->id, old('thu_category_ids', [])) ? 'checked' : '' }} class="rounded border-gray-300">
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
                                    <input type="checkbox" name="chi_category_ids[]" value="{{ $c->id }}" {{ in_array($c->id, old('chi_category_ids', [])) ? 'checked' : '' }} class="rounded border-gray-300">
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

    @foreach($tongquanStatistics as $item)
        @php
            $stat = $item['stat'];
            $from = $item['from'];
            $to = $item['to'];
            $data = $item['data'];
            $thuTotal = (float) ($data['thuTotal'] ?? 0);
            $chiTotal = (float) ($data['chiTotal'] ?? 0);
            $loiNhuan = (float) ($data['loiNhuan'] ?? 0);
            $chartDates = $data['chartDates'] ?? [];
            $chartThu = $data['chartThu'] ?? [];
            $chartChi = $data['chartChi'] ?? [];
            $chartLoiNhuan = $data['chartLoiNhuan'] ?? [];
            $chartId = 'tongquan-chart-' . $stat->id;
            $thuIds = $stat->thu_category_ids ?? [];
            $chiIds = $stat->chi_category_ids ?? [];
        @endphp
        <div class="mb-8 rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800"
             x-data="{ editOpen: false, chartOpen: (typeof localStorage !== 'undefined' && localStorage.getItem('tc_chart_{{ $stat->id }}') === '1') }"
             x-init="if(chartOpen) $nextTick(() => setTimeout(() => window.renderTongquanCharts && window.renderTongquanCharts(), 50))">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 p-4 dark:border-gray-700">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $stat->name }}</h4>
                <div class="flex items-center gap-2">
                    <button type="button" @click="editOpen = true" class="text-sm text-brand-600 hover:underline dark:text-brand-400">Sửa</button>
                    <form method="post" action="{{ route('tai-chinh.tongquan-statistic.destroy', $stat) }}" class="inline" onsubmit="return confirm('Xóa thống kê này?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-red-600 hover:underline dark:text-red-400">Xóa</button>
                    </form>
                </div>
            </div>
            {{-- Form sửa inline — mở ngay khi bấm Sửa, không reload trang --}}
            <div x-show="editOpen" x-cloak x-transition class="border-b border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50" style="display: none;">
                <form method="post" action="{{ route('tai-chinh.tongquan-statistic.update', $stat) }}" class="flex flex-wrap gap-6">
                    @csrf
                    @method('PUT')
                    <div class="min-w-[200px]">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Tên thống kê</label>
                        <input type="text" name="name" value="{{ old('name', $stat->name) }}" required maxlength="255" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="VD: Thu chi tháng 3">
                    </div>
                    <div class="min-w-[200px]">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Kỳ</label>
                        <select name="period" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            @foreach($periods as $val => $label)
                                <option value="{{ $val }}" {{ ($stat->period ?? 'thang') === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-[200px]">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Từ ngày</label>
                        <input type="date" name="from_date" value="{{ $stat->from_date?->format('Y-m-d') }}" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    </div>
                    <div class="min-w-[200px]">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Đến ngày</label>
                        <input type="date" name="to_date" value="{{ $stat->to_date?->format('Y-m-d') }}" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    </div>
                    <div class="min-w-[220px]">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Danh mục thu</label>
                        <div class="max-h-40 overflow-y-auto rounded-lg border border-gray-200 bg-white p-2 dark:border-gray-600 dark:bg-gray-800">
                            @foreach($danhMucThu as $c)
                                <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <input type="checkbox" name="thu_category_ids[]" value="{{ $c->id }}" {{ in_array($c->id, $thuIds) ? 'checked' : '' }} class="rounded border-gray-300">
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
                                    <input type="checkbox" name="chi_category_ids[]" value="{{ $c->id }}" {{ in_array($c->id, $chiIds) ? 'checked' : '' }} class="rounded border-gray-300">
                                    <span class="text-sm text-gray-900 dark:text-white">{{ $c->name }}</span>
                                </label>
                            @endforeach
                            @if($danhMucChi->isEmpty())
                                <p class="text-xs text-gray-500 dark:text-gray-400">Chưa có danh mục chi.</p>
                            @endif
                        </div>
                    </div>
                    <div class="flex w-full items-end gap-2">
                        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Cập nhật</button>
                        <button type="button" @click="editOpen = false" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Hủy</button>
                    </div>
                </form>
            </div>
            <div class="p-4">
                <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">Kỳ: {{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }}</p>
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
                <div class="mt-4">
                    <button type="button" @click="chartOpen = !chartOpen; try { localStorage.setItem('tc_chart_{{ $stat->id }}', chartOpen ? '1' : '0'); } catch(e) {}; if(chartOpen) $nextTick(() => window.renderTongquanCharts && window.renderTongquanCharts())" class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-brand-600 dark:hover:text-brand-400">
                        <span>Biểu đồ theo ngày</span>
                        <span x-show="!chartOpen" x-cloak style="display: none;">▼ Mở rộng</span>
                        <span x-show="chartOpen" x-cloak style="display: none;">▲ Thu gọn</span>
                    </button>
                    <div x-show="chartOpen" x-cloak x-transition class="rounded-xl border border-gray-200 bg-white p-4 pr-8 dark:border-gray-700 dark:bg-gray-800" style="display: none;">
                        <div class="min-w-0 mt-2">
                            <div id="{{ $chartId }}" class="tongquan-stat-chart min-h-[280px] w-full" data-dates="{{ json_encode($chartDates) }}" data-thu="{{ json_encode($chartThu) }}" data-chi="{{ json_encode($chartChi) }}" data-loinhuan="{{ json_encode($chartLoiNhuan) }}"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    @if(empty($tongquanStatistics))
        <p class="text-sm text-gray-500 dark:text-gray-400">Chưa có thống kê. Nhấn nút + để mở form Tạo thống kê.</p>
    @endif
</div>

<script>
(function() {
    function renderTongquanCharts() {
        document.querySelectorAll('.tongquan-stat-chart').forEach(function(el) {
            if (el._rendered) return;
            if (el.offsetParent === null) return;
            var dates = JSON.parse(el.getAttribute('data-dates') || '[]');
            var thu = JSON.parse(el.getAttribute('data-thu') || '[]');
            var chi = JSON.parse(el.getAttribute('data-chi') || '[]');
            var loiNhuan = JSON.parse(el.getAttribute('data-loinhuan') || '[]');
            if (typeof window.ApexCharts === 'undefined') {
                var s = document.createElement('script');
                s.src = 'https://cdn.jsdelivr.net/npm/apexcharts@3.45.0/dist/apexcharts.min.js';
                s.onload = function() { renderTongquanCharts(); };
                document.head.appendChild(s);
                return;
            }
            el._rendered = true;
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
        });
    }
    window.renderTongquanCharts = renderTongquanCharts;
    if (document.readyState === 'complete') renderTongquanCharts();
    else window.addEventListener('load', renderTongquanCharts);

    (function initTcDatePickers() {
        function run() {
            if (typeof window.flatpickr === 'undefined') return;
            var fromEl = document.getElementById('tc-from-date');
            var toEl = document.getElementById('tc-to-date');
            if (!fromEl || !toEl) return;
            var opts = { dateFormat: 'Y-m-d', allowInput: false, appendTo: document.body, static: false };
            if (window.flatpickr.l10ns && window.flatpickr.l10ns.vn) opts.locale = 'vn';
            window.flatpickr(fromEl, opts);
            window.flatpickr(toEl, opts);
        }
        if (document.readyState === 'complete') run();
        else window.addEventListener('load', run);
    })();
})();
</script>
