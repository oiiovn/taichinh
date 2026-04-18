@extends('layouts.food')

@section('foodContent')
@php
    $fmt = fn ($n) => \App\Helpers\BaoCaoHelper::formatGiaVonNguyen($n);
@endphp
<div class="space-y-6" x-data="{ filterType: 'all' }">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Phân tích khách hàng</h2>

    <div class="flex flex-wrap items-end gap-2 pr-2 sm:pr-0">
        <form action="{{ route('food.khach-hang') }}" method="get" class="flex flex-wrap items-center gap-2 min-w-0">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Từ</label>
            <input type="date" name="from_date" value="{{ $from->format('Y-m-d') }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white min-w-0">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">đến</label>
            <input type="date" name="to_date" value="{{ $to->format('Y-m-d') }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white min-w-0">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Nhóm Cohort</label>
            <select name="cohort_by" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <option value="day" {{ ($cohortBy ?? 'month') === 'day' ? 'selected' : '' }}>Ngày</option>
                <option value="week" {{ ($cohortBy ?? 'month') === 'week' ? 'selected' : '' }}>Tuần</option>
                <option value="month" {{ ($cohortBy ?? 'month') === 'month' ? 'selected' : '' }}>Tháng</option>
            </select>
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm text-white hover:bg-brand-700 shrink-0">Xem</button>
        </form>
    </div>

    {{-- Block tóm tắt: Phân loại theo HÀNH VI trong kỳ (order_count) --}}
    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Tổng quan kỳ {{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }}</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">Tổng khách có ≥2 đơn (all-time): <strong>{{ $returningCount ?? 0 }}</strong></p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Khách có 1 đơn trong kỳ</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $khachMoiCount }}</p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Khách mới (order_count = 1)</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Khách có đơn ≥2 trong kỳ</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $khachCoDonTu2Count }}</p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Đo retention thực</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Tăng trưởng khách quay lại</p>
                <p class="mt-1 text-2xl font-semibold {{ ($tangTruongReturning ?? 0) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">{{ ($tangTruongReturning ?? 0) >= 0 ? '+' : '' }}{{ $tangTruongReturning ?? 0 }}%</p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Số khách ≥2 đơn so kỳ trước</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Doanh thu từ khách quay lại</p>
                <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $fmt($doanhThuTuKhachQuayLai ?? 0) }} đ</p>
                <p class="mt-0.5 text-xs {{ ($tangTruongDoanhThuReturning ?? 0) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">{{ ($tangTruongDoanhThuReturning ?? 0) >= 0 ? '+' : '' }}{{ $tangTruongDoanhThuReturning ?? 0 }}% so kỳ trước</p>
            </div>
        </div>
        {{-- Repeat Rate chuẩn + Time to 2nd order all-time + % quay lại 3/7 ngày --}}
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 border-t border-gray-200 dark:border-gray-600 pt-4">
            <div class="rounded-lg border border-amber-200 bg-amber-50/50 p-3 dark:border-amber-800 dark:bg-amber-900/20">
                <p class="text-xs font-medium text-amber-800 dark:text-amber-200">Repeat Rate (chuẩn)</p>
                <p class="mt-0.5 text-lg font-semibold text-amber-900 dark:text-amber-100">{{ $repeatRate ?? 0 }}%</p>
                <p class="text-xs text-amber-700 dark:text-amber-300">Công thức: khách quay lại trong kỳ / khách đã từng mua trước kỳ (loại khách mới khỏi mẫu)</p>
            </div>
            @if(isset($avgTimeToSecondOrder))
            <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800/50">
                <p class="text-xs font-medium text-gray-700 dark:text-gray-300">Thời gian tới đơn thứ 2 (all-time)</p>
                <p class="mt-0.5 text-lg font-semibold text-gray-900 dark:text-white">{{ $avgTimeToSecondOrder }} ngày</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Trung bình số ngày từ đơn đầu đến đơn thứ 2, tính trên toàn bộ khách có >=2 đơn</p>
            </div>
            @endif
            @if(isset($pctQuayLaiTrong3Ngay))
            <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800/50">
                <p class="text-xs font-medium text-gray-700 dark:text-gray-300">Quay lại trong 3 ngày</p>
                <p class="mt-0.5 text-lg font-semibold text-gray-900 dark:text-white">{{ $pctQuayLaiTrong3Ngay }}%</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">% khách có đơn 2 trong 3 ngày</p>
            </div>
            @endif
            @if(isset($pctQuayLaiTrong7Ngay))
            <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800/50">
                <p class="text-xs font-medium text-gray-700 dark:text-gray-300">Quay lại trong 7 ngày</p>
                <p class="mt-0.5 text-lg font-semibold text-gray-900 dark:text-white">{{ $pctQuayLaiTrong7Ngay }}%</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">% khách có đơn 2 trong 7 ngày</p>
            </div>
            @endif
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Vòng đời khách hàng (toàn thời gian)</h3>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Returning: quay lại sau khoảng nghỉ >= {{ $lifecycleMetrics['returning_gap_days'] ?? 30 }} ngày · Churned: không mua hơn {{ $lifecycleMetrics['churn_days'] ?? 30 }} ngày · Loyal: tổng đơn all-time >= {{ $lifecycleMetrics['loyal_order_threshold'] ?? 8 }}</p>
        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800/50">
                <p class="text-xs text-gray-500 dark:text-gray-400">Khách mới trong kỳ</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ $lifecycleMetrics['new_in_period'] ?? 0 }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Đơn đầu tiên nằm trong khoảng thời gian đang xem</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800/50">
                <p class="text-xs text-gray-500 dark:text-gray-400">Khách hoạt động trong kỳ</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ $lifecycleMetrics['active_in_period'] ?? 0 }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Có đơn trong kỳ và đã mua trước đó, nhưng chưa đủ gap để tính Returning</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800/50">
                <p class="text-xs text-gray-500 dark:text-gray-400">Khách quay lại trong kỳ</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ $lifecycleMetrics['returning_in_period'] ?? 0 }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Đã từng mua trước kỳ và quay lại sau một khoảng nghỉ đủ dài</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800/50">
                <p class="text-xs text-gray-500 dark:text-gray-400">Khách rời bỏ (all-time)</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ $lifecycleMetrics['churned'] ?? 0 }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Không phát sinh đơn mới trong hơn ngưỡng churn</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800/50">
                <p class="text-xs text-gray-500 dark:text-gray-400">Khách trung thành (all-time)</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ $lifecycleMetrics['loyal'] ?? 0 }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Tổng số đơn đạt ngưỡng loyal</p>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800/50">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Giữ chân theo Cohort (ngày đơn đầu {{ ($cohortBy ?? 'month') === 'day' ? 'theo ngày' : (($cohortBy ?? 'month') === 'week' ? 'theo tuần' : 'theo tháng') }})</h3>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">D0 luôn là 100%. D3/D7/D30 là tỷ lệ khách có đơn quay lại trong 3/7/30 ngày kể từ đơn đầu tiên.</p>
        <div class="mt-3 overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="w-full min-w-[560px] text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Cohort</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Số khách</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">D0</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">D3</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">D7</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">D30</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($cohortRows ?? []) as $row)
                        <tr class="border-b border-gray-100 dark:border-gray-700/50">
                            <td class="px-4 py-2 text-gray-900 dark:text-white">{{ $row['cohort'] }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $row['size'] }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $row['d0_pct'] }}%</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $row['d3_pct'] }}%</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $row['d7_pct'] }}%</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $row['d30_pct'] }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Chưa có dữ liệu cohort.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Bộ lọc: Tất cả / Khách mới / Khách có ≥2 đơn --}}
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Hiển thị:</span>
        <button type="button" @click="filterType = 'all'" :class="filterType === 'all' ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'" class="rounded-lg px-3 py-1.5 text-sm font-medium">Tất cả</button>
        <button type="button" @click="filterType = 'new'" :class="filterType === 'new' ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'" class="rounded-lg px-3 py-1.5 text-sm font-medium">Chỉ khách 1 đơn</button>
        <button type="button" @click="filterType = 'returning'" :class="filterType === 'returning' ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'" class="rounded-lg px-3 py-1.5 text-sm font-medium">Chỉ khách ≥2 đơn</button>
    </div>

    {{-- Top khách: bảng xếp hạng theo số đơn, doanh thu --}}
    <div>
        <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Top khách hàng (sắp xếp theo số đơn, doanh thu)</h3>
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="w-full min-w-[920px] text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">#</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Khách hàng</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Số đơn</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300" title="Số ngày khác nhau có đơn (không nhân đôi cùng ngày)">Ngày có đơn</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Tổng doanh thu</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300" title="Ngày phát sinh đơn đầu tiên trong toàn bộ lịch sử">Đơn đầu all-time</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Đơn đầu</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Đơn gần nhất</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300" title="Số ngày từ đơn gần nhất đến hôm nay; càng nhỏ càng mới">Recency (ngày)</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300" title="Ngày có đơn / (hôm nay − đơn đầu) × 30; chỉ khi ≥2 ngày có đơn">Nhịp độ ngày (ước/tháng)</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Khoảng cách TB (ngày)</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300" title="Trạng thái vòng đời dựa trên dữ liệu all-time">Vòng đời</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Nhóm all-time</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $i => $c)
                        <tr class="border-b border-gray-100 dark:border-gray-700/50"
                            x-show="filterType === 'all' || (filterType === 'new' && {{ $c['is_new_in_period'] ? 'true' : 'false' }}) || (filterType === 'returning' && {{ $c['is_returning_in_period'] ? 'true' : 'false' }})">
                            <td class="px-4 py-2 text-gray-500 dark:text-gray-400">{{ $i + 1 }}</td>
                            <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">{{ $c['name'] }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $c['order_count'] }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $c['unique_order_days'] ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-900 dark:text-white">{{ $fmt($c['total_revenue']) }} đ</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ isset($c['first_order_all_time']) && $c['first_order_all_time'] ? $c['first_order_all_time']->format('d/m/Y') : '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $c['first_order_date'] ? $c['first_order_date']->format('d/m/Y') : '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $c['last_order_date'] ? $c['last_order_date']->format('d/m/Y') : '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ isset($c['recency_days']) && $c['recency_days'] !== null ? $c['recency_days'] : '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $c['orders_per_month'] !== null ? $c['orders_per_month'] : '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $c['avg_days_between_orders'] !== null ? $c['avg_days_between_orders'] . ' ngày' : '—' }}</td>
                            <td class="px-4 py-2">
                                @if(($c['lifecycle_status'] ?? '') === 'new')
                                    <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-300">Mới</span>
                                @elseif(($c['lifecycle_status'] ?? '') === 'returning')
                                    <span class="inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">Quay lại</span>
                                @else
                                    <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">Hoạt động</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                @if(($c['order_segment'] ?? '') === 'new')
                                    <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-300">1 đơn</span>
                                @elseif(($c['order_segment'] ?? '') === 'early-repeat')
                                    <span class="inline-flex rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-800 dark:bg-sky-900/30 dark:text-sky-300">2-3 đơn</span>
                                @elseif(($c['order_segment'] ?? '') === 'repeat')
                                    <span class="inline-flex rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300">4-7 đơn</span>
                                @else
                                    <span class="inline-flex rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-800 dark:bg-violet-900/30 dark:text-violet-300">8+ Trung thành</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="14" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Chưa có dữ liệu đơn hàng trong khoảng thời gian này.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
