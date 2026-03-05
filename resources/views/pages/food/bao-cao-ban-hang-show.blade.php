@extends('layouts.food')

@section('foodContent')
@php
    $fmtNguyen = fn ($n) => \App\Helpers\BaoCaoHelper::formatGiaVonNguyen($n);
    $fmt = fn ($n) => \App\Helpers\BaoCaoHelper::formatGiaVon($n);
    $displayTotalCost = $display_total_cost ?? (float) $report->total_cost;
    $displayTienCong = $display_total_tien_cong ?? (float) $report->total_tien_cong;
    $displayBonus = (float) ($report->bonus ?? 0);
    $quyetToan = $displayTotalCost + $displayTienCong + $displayBonus;
@endphp
<div class="space-y-6">
    {{-- Header báo cáo --}}
    <div class="flex flex-wrap items-start justify-between gap-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ $report->report_code }}
            @if($displayBonus > 0)
                <span class="ml-2 inline-flex rounded-full bg-emerald-500 px-2 py-0.5 text-xs font-medium text-white dark:bg-emerald-600" title="Thưởng">+{{ $fmtNguyen($displayBonus) }} đ</span>
            @endif
        </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Ngày {{ $report->report_date->format('d/m/Y') }}</p>
            <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $report->total_orders }} đơn hàng</p>
            <div class="mt-3 space-y-1 text-sm">
                <p class="text-gray-700 dark:text-gray-300">Tổng vốn: <span class="font-medium">{{ $fmtNguyen($displayTotalCost) }} đ</span></p>
                <p class="text-gray-700 dark:text-gray-300">Tiền công: <span class="font-medium">{{ $fmtNguyen($displayTienCong) }} đ</span></p>
                @if($displayBonus > 0)
                    <p class="text-gray-700 dark:text-gray-300">Thưởng: <span class="font-medium">{{ $fmtNguyen($displayBonus) }} đ</span></p>
                @endif
                <p class="text-gray-700 dark:text-gray-300">Quyết toán: <span class="font-medium">{{ $fmtNguyen($quyetToan) }} đ</span></p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if($canManage ?? true)
                <form action="{{ route('food.bao-cao-ban-hang.cong-no.store', $report) }}" method="POST" class="inline" x-data="{ open: false }">
                    @csrf
                    <button type="button" @click="open = true" class="rounded-lg border border-amber-300 px-4 py-2 text-sm font-medium text-amber-700 hover:bg-amber-50 dark:border-amber-700 dark:text-amber-400 dark:hover:bg-amber-900/20">Xử lý công nợ</button>
                    <template x-teleport="body">
                        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @keydown.escape.window="open = false">
                            <div x-show="open" x-transition class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800" @click.stop>
                                <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Tạo công nợ</h3>
                                <form method="POST" action="{{ route('food.bao-cao-ban-hang.cong-no.store', $report) }}">
                                    @csrf
                                    <div class="mb-4">
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Chọn user</label>
                                        <select name="debtor_user_id" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                            <option value="">-- Chọn user --</option>
                                            @foreach($users ?? [] as $u)
                                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-4">
                                        <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                            <input type="checkbox" name="only_tien_cong" value="1" class="rounded border-gray-300">
                                            Chỉ trả tiền công
                                        </label>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Tạo công nợ cho user đó</button>
                                        <button type="button" @click="open = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Hủy</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </template>
                </form>
                <form action="{{ route('food.bao-cao-ban-hang.destroy', $report) }}" method="POST" class="inline" onsubmit="return confirm('Xóa báo cáo {{ $report->report_code }}?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-900/20">Xóa</button>
                </form>
            @endif
            <a href="{{ ($canManage ?? true) ? route('food.bao-cao-ban-hang') : route('food.cong-no') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">Đóng</a>
        </div>
    </div>

    @if(($report->debts ?? collect())->isNotEmpty())
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <h3 class="mb-3 text-sm font-semibold text-gray-800 dark:text-gray-200">Công nợ</h3>
            <ul class="space-y-2 text-sm">
                @foreach($report->debts as $d)
                    <li class="flex flex-wrap items-center gap-2">
                        <span class="text-gray-700 dark:text-gray-300">{{ $d->debtor?->name }}:</span>
                        @if($d->payment)
                            <span class="text-green-600 dark:text-green-400">Đã thanh toán</span>
                        @else
                            <span class="text-amber-600 dark:text-amber-400">Chưa thanh toán</span>
                            <form action="{{ route('food.cong-no.thanh-toan-tien-mat', $d) }}" method="POST" class="inline" onsubmit="return confirm('Ghi nhận thanh toán tiền mặt {{ $fmtNguyen($d->debt_amount) }} đ?');">
                                @csrf
                                <button type="submit" class="text-emerald-600 hover:underline dark:text-emerald-400">Thanh toán tiền mặt</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Từng đơn hàng --}}
    @foreach($orders as $order)
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
            <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                <span class="text-gray-700 dark:text-gray-300">Đơn hàng: <strong>{{ $order['ma_hoa_don'] }}</strong></span>
                <span class="text-gray-700 dark:text-gray-300">Khách: {{ $order['khach_hang'] ?? '—' }}</span>
                <span class="text-gray-700 dark:text-gray-300">Thời gian: {{ $order['thoi_gian'] ?? '—' }}</span>
                <span class="text-gray-700 dark:text-gray-300">{{ count($order['items']) }} dòng</span>
                <span class="text-gray-700 dark:text-gray-300">Tổng vốn của đơn: <strong>{{ $fmtNguyen($order['total_cost']) }} đ</strong></span>
                <span class="text-gray-600 dark:text-gray-400">Tiền công đơn: {{ $fmtNguyen($order['tien_cong']) }} đ</span>
            </div>
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                        <tr>
                            <th class="px-3 py-2 font-medium text-gray-700 dark:text-gray-300">Nhóm</th>
                            <th class="px-3 py-2 font-medium text-gray-700 dark:text-gray-300">Mã hàng</th>
                            <th class="px-3 py-2 font-medium text-gray-700 dark:text-gray-300">Tên hàng</th>
                            <th class="px-3 py-2 font-medium text-gray-700 dark:text-gray-300">SL</th>
                            <th class="px-3 py-2 font-medium text-gray-700 dark:text-gray-300">Giá vốn/SL</th>
                            <th class="px-3 py-2 font-medium text-gray-700 dark:text-gray-300">Ghi chú</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order['items'] as $row)
                            <tr class="border-b border-gray-100 dark:border-gray-700/50">
                                <td class="px-3 py-2 text-gray-900 dark:text-white">{{ $row['item']->nhom_hang ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-900 dark:text-white">{{ $row['item']->ma_hang ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-900 dark:text-white">{{ $row['item']->ten_hang ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-900 dark:text-white">{{ $row['sl'] }}</td>
                                <td class="px-3 py-2 text-gray-900 dark:text-white">{{ $fmt($row['gia_von_unit']) }} đ</td>
                                <td class="px-3 py-2 text-gray-500 dark:text-gray-400">—</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</div>
@endsection
