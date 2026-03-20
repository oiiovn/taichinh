@extends('layouts.food')

@section('foodContent')
@php
    $fmt = fn ($n) => \App\Helpers\BaoCaoHelper::formatGiaVonNguyen($n);
@endphp
<div class="space-y-6">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Thống kê Nhân viên tăng đánh giá</h2>

    <form method="GET" action="{{ route('food.thong-ke-buff') }}" class="flex flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Từ ngày</label>
            <input type="date" name="from_date" value="{{ $from->format('Y-m-d') }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Đến ngày</label>
            <input type="date" name="to_date" value="{{ $to->format('Y-m-d') }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
        </div>
        <div class="min-w-[220px]">
            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Chi nhánh</label>
            <select name="food_branch_id" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <option value="">Tất cả chi nhánh</option>
                @foreach($branches as $br)
                    <option value="{{ $br->id }}" @selected((int) $branchId === (int) $br->id)>{{ $br->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Xem</button>
    </form>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"><p class="text-xs text-gray-500 dark:text-gray-400">Số đơn Buff</p><p class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $tongDon }}</p></div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"><p class="text-xs text-gray-500 dark:text-gray-400">Tiền Buff</p><p class="mt-1 text-xl font-semibold text-amber-600 dark:text-amber-400">{{ $fmt($tongBuff) }} đ</p></div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"><p class="text-xs text-gray-500 dark:text-gray-400">Tiền công</p><p class="mt-1 text-xl font-semibold text-sky-600 dark:text-sky-400">{{ $fmt($tongTienCong) }} đ</p></div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"><p class="text-xs text-gray-500 dark:text-gray-400">Tổng chi</p><p class="mt-1 text-xl font-semibold text-red-600 dark:text-red-400">{{ $fmt($tongChi) }} đ</p></div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full min-w-[820px] text-left text-sm">
            <thead class="border-b border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Mã hóa đơn</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Chi nhánh</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Ngày đơn</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Nhân viên</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Chủ quán</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Buff</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Tiền công</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $o)
                    <tr class="border-b border-gray-100 dark:border-gray-700/50">
                        <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">{{ $o->invoice_code }}</td>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $o->branch?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $o->order_date?->format('d/m/Y') ?? '—' }}@if($o->order_time_text)<span class="text-gray-400"> ({{ $o->order_time_text }})</span>@endif</td>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $o->customer_name ?: '—' }}</td>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $o->receiver_name ?: '—' }}</td>
                        <td class="px-4 py-2 text-amber-600 dark:text-amber-400">{{ $fmt($o->buff_amount) }} đ</td>
                        <td class="px-4 py-2 text-sky-600 dark:text-sky-400">{{ $fmt($o->labor_amount) }} đ</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Không có đơn Buff trong kỳ đã chọn.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
