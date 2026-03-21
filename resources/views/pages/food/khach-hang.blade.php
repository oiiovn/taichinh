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
        {{-- True Repeat Rate + Time to 2nd order + % quay lại 3/7 ngày --}}
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 border-t border-gray-200 dark:border-gray-600 pt-4">
            <div class="rounded-lg border border-amber-200 bg-amber-50/50 p-3 dark:border-amber-800 dark:bg-amber-900/20">
                <p class="text-xs font-medium text-amber-800 dark:text-amber-200">True Repeat Rate</p>
                <p class="mt-0.5 text-lg font-semibold text-amber-900 dark:text-amber-100">{{ $trueRepeatRate ?? 0 }}%</p>
                <p class="text-xs text-amber-700 dark:text-amber-300">Khách ≥2 đơn / tổng khách trong kỳ</p>
            </div>
            @if(isset($avgTimeToSecondOrder))
            <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800/50">
                <p class="text-xs font-medium text-gray-700 dark:text-gray-300">Time to 2nd order</p>
                <p class="mt-0.5 text-lg font-semibold text-gray-900 dark:text-white">{{ $avgTimeToSecondOrder }} ngày</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Trung bình từ đơn đầu → đơn thứ 2</p>
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
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Đơn đầu</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Đơn gần nhất</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300" title="Số ngày từ đơn gần nhất đến hôm nay">Recency (ngày)</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300" title="Ngày có đơn / (hôm nay − đơn đầu) × 30; chỉ khi ≥2 ngày có đơn">Nhịp độ ngày (ước/tháng)</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Khoảng cách TB (ngày)</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Phân loại</th>
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
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $c['first_order_date'] ? $c['first_order_date']->format('d/m/Y') : '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $c['last_order_date'] ? $c['last_order_date']->format('d/m/Y') : '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ isset($c['recency_days']) && $c['recency_days'] !== null ? $c['recency_days'] : '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $c['orders_per_month'] !== null ? $c['orders_per_month'] : '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $c['avg_days_between_orders'] !== null ? $c['avg_days_between_orders'] . ' ngày' : '—' }}</td>
                            <td class="px-4 py-2">
                                @if($c['is_loyal_in_period'] ?? false)
                                    <span class="inline-flex rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-800 dark:bg-violet-900/30 dark:text-violet-300">Trung thành</span>
                                @elseif($c['is_returning_in_period'])
                                    <span class="inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">Quay lại</span>
                                @else
                                    <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-300">Mới</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Chưa có dữ liệu đơn hàng trong khoảng thời gian này.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
