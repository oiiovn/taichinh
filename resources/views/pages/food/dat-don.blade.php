@extends('layouts.food')

@section('foodContent')
@php
    $fmt = fn ($n) => \App\Helpers\BaoCaoHelper::formatGiaVonNguyen($n);
    $branchBadgeStyles = [
        'background-color:#ffe4e6;color:#be123c;border:1px solid #fecdd3;',
        'background-color:#ffedd5;color:#c2410c;border:1px solid #fed7aa;',
        'background-color:#fef3c7;color:#b45309;border:1px solid #fde68a;',
        'background-color:#ecfccb;color:#4d7c0f;border:1px solid #d9f99d;',
        'background-color:#d1fae5;color:#047857;border:1px solid #a7f3d0;',
        'background-color:#cffafe;color:#0e7490;border:1px solid #a5f3fc;',
        'background-color:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;',
        'background-color:#e0e7ff;color:#4338ca;border:1px solid #c7d2fe;',
        'background-color:#ede9fe;color:#6d28d9;border:1px solid #ddd6fe;',
        'background-color:#fae8ff;color:#a21caf;border:1px solid #f5d0fe;',
    ];
    $lastForm = is_array($lastForm ?? null) ? $lastForm : [];
    $defaultOrderDate = old('order_date', $lastForm['order_date'] ?? now()->format('Y-m-d'));
    $defaultBranchId = old('food_branch_id', $lastForm['food_branch_id'] ?? '');
    $defaultCustomerName = old('customer_name', $lastForm['customer_name'] ?? '');
    $defaultProduct = old('product_name', $lastForm['product_name'] ?? $defaultProductName ?? 'Quán Ship Bù');
    $cooldownRemaining = max(0, (int) ($cooldownRemaining ?? 0));
@endphp

<div class="min-h-[calc(100vh-140px)] bg-[#f7f7f8] p-2 dark:bg-gray-900/20 sm:p-4">
<div class="mx-auto max-w-[560px] space-y-4">
    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">{{ session('error') }}</div>
    @endif

    <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Tạo đơn hàng mới</h2>

    @if(($todayScheduleQuotaByBranch ?? collect())->isNotEmpty())
        <div class="rounded-2xl border border-orange-200 bg-orange-50/80 p-4 shadow-sm dark:border-orange-800 dark:bg-orange-900/20">
            <p class="text-sm font-semibold text-orange-900 dark:text-orange-100">Lịch đặt đơn hôm nay</p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                @foreach($todayScheduleQuotaByBranch as $quota)
                    @php
                        $quotaBadgeStyle = $branchBadgeStyles[abs(crc32(mb_strtolower((string) $quota['branch_name']))) % count($branchBadgeStyles)];
                    @endphp
                    <span class="inline-flex items-center rounded-md px-2 py-1 font-medium" style="{{ $quotaBadgeStyle }}">
                        {{ $quota['branch_name'] }}: {{ $quota['order_count'] }} đơn
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('food.dat-don.store') }}" x-data="{ cooldown: {{ $cooldownRemaining }} }" x-init="if (cooldown > 0) { const t = setInterval(() => { cooldown = Math.max(0, cooldown - 1); if (cooldown === 0) clearInterval(t); }, 1000); }" class="rounded-2xl border border-gray-200 bg-white p-5 pb-24 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:pb-5">
        @csrf
        <div class="space-y-5">
            <div class="space-y-3 rounded-2xl border border-gray-100 bg-gray-50/70 p-4 dark:border-gray-700 dark:bg-gray-900/30">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Thông tin đơn</p>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Ngày đặt <span class="text-red-500">*</span></label>
                <div class="relative">
                    <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2v11a2 2 0 002 2z"/></svg>
                    </span>
                    <input type="date" name="order_date" value="{{ $defaultOrderDate }}" required class="w-full rounded-xl border border-gray-200 bg-white py-3 pl-12 pr-3 text-sm text-gray-900 outline-none transition focus:border-orange-400 focus:ring-4 focus:ring-orange-100 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:ring-orange-900/40">
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Chi nhánh <span class="text-red-500">*</span></label>
                <div class="relative">
                    <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M5 21V7l8-4 6 3v15M9 9h.01M9 13h.01M9 17h.01M13 9h.01M13 13h.01M13 17h.01"/></svg>
                    </span>
                <select name="food_branch_id" required class="w-full rounded-xl border border-gray-200 bg-white py-3 pl-12 pr-3 text-sm text-gray-900 outline-none transition focus:border-orange-400 focus:ring-4 focus:ring-orange-100 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:ring-orange-900/40">
                    <option value="">Chọn chi nhánh</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) $defaultBranchId === (int) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Mã đơn <span class="text-red-500">*</span></label>
                <input type="text" value="Tự sinh tự động" readonly class="w-full cursor-not-allowed rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">ShopeeFood <span class="text-red-500">*</span></label>
                <select name="customer_name" required class="w-full rounded-xl border border-gray-200 bg-white px-3 py-3 text-sm text-gray-900 outline-none transition focus:border-orange-400 focus:ring-4 focus:ring-orange-100 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:ring-orange-900/40">
                    <option value="">Chọn tài khoản ShopeeFood</option>
                    @foreach(($customerOptions ?? []) as $name)
                        <option value="{{ $name }}" @selected($defaultCustomerName === $name)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            </div>

            <div class="space-y-3 rounded-2xl border border-gray-100 bg-gray-50/70 p-4 dark:border-gray-700 dark:bg-gray-900/30">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Thông tin sản phẩm</p>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Sản phẩm <span class="text-red-500">*</span></label>
                <select name="product_name" required class="w-full rounded-xl border border-gray-200 bg-white px-3 py-3 text-sm text-gray-900 outline-none transition focus:border-orange-400 focus:ring-4 focus:ring-orange-100 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:ring-orange-900/40">
                    <option value="Quán Ship Bù" @selected($defaultProduct === 'Quán Ship Bù')>Quán Ship Bù</option>
                </select>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Thu nhập <span class="text-red-500">*</span></label>
                <div class="relative">
                    <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-2.21 0-4 .895-4 2s1.79 2 4 2 4 .895 4 2-1.79 2-4 2m0-10V6m0 12v-2"/></svg>
                    </span>
                <input type="text" value="10.000" readonly class="w-full cursor-not-allowed rounded-xl border border-gray-200 bg-gray-50 py-3 pl-12 pr-3 text-sm text-gray-700 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300">
                </div>
            </div>
            </div>
        </div>
        <p class="mt-4 text-xs text-amber-600 dark:text-amber-400" x-show="cooldown > 0" x-cloak>Vui lòng chờ <span x-text="cooldown"></span> giây để tạo đơn tiếp theo.</p>
        <div class="mt-4 hidden sm:block">
            <button type="submit" :disabled="cooldown > 0" class="w-full rounded-xl bg-[#EE4D2D] px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#d94324] active:translate-y-[1px] disabled:cursor-not-allowed disabled:opacity-60">Tạo đơn ngay</button>
        </div>
        <div class="fixed inset-x-0 bottom-0 z-20 border-t border-gray-200 bg-white/95 p-3 backdrop-blur dark:border-gray-700 dark:bg-gray-900/95 sm:hidden">
            <button type="submit" :disabled="cooldown > 0" class="w-full rounded-xl bg-[#EE4D2D] px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#d94324] active:translate-y-[1px] disabled:cursor-not-allowed disabled:opacity-60">Tạo đơn ngay</button>
        </div>
    </form>

    <form method="GET" action="{{ route('food.dat-don') }}" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Bộ lọc</p>
        <div class="flex flex-wrap items-end gap-2">
            <div class="min-w-[140px] flex-1">
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Từ ngày</label>
                <input type="date" name="from_date" value="{{ $from->format('Y-m-d') }}" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:ring-blue-900/40">
            </div>
            <div class="min-w-[140px] flex-1">
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Đến ngày</label>
                <input type="date" name="to_date" value="{{ $to->format('Y-m-d') }}" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:ring-blue-900/40">
            </div>
            <button type="submit" class="rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-700">Lọc</button>
        </div>
    </form>

    <div class="space-y-3">
        @forelse($orders as $o)
            @php
                $branchName = trim((string) ($o->branch?->name ?? '—'));
                $branchBadgeStyle = $branchBadgeStyles[abs(crc32(mb_strtolower($branchName))) % count($branchBadgeStyles)];
            @endphp
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Mã đơn</p>
                        <p class="font-mono text-sm font-semibold text-gray-900 dark:text-white">{{ $o->invoice_code }}</p>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $o->order_date?->format('d/m/Y') ?? '—' }}{{ $o->order_time_text ? ' '.$o->order_time_text : '' }}</p>
                </div>
                <div class="mt-3 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                    <p class="text-gray-700 dark:text-gray-300"><span class="text-gray-500 dark:text-gray-400">Sản phẩm:</span> {{ $o->product_name ?: 'Quán Ship Bù' }}</p>
                    <p class="text-gray-700 dark:text-gray-300">
                        <span class="text-gray-500 dark:text-gray-400">Chi nhánh:</span>
                        <span class="ml-1 inline-block rounded-md px-1.5 py-0.5 text-xs" style="{{ $branchBadgeStyle }}">{{ $branchName }}</span>
                    </p>
                    <p class="text-gray-900 dark:text-white"><span class="text-gray-500 dark:text-gray-400">Shopeefood:</span> {{ $o->customer_name }}</p>
                    <p class="text-green-600 dark:text-green-400"><span class="text-gray-500 dark:text-gray-400">Thu nhập:</span> {{ $fmt($o->labor_amount) }} đ</p>
                </div>
            </div>
        @empty
            <p class="rounded-2xl border border-gray-200 bg-white px-4 py-6 text-center text-sm text-gray-500 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Chưa có đơn nào trong kỳ lọc.</p>
        @endforelse
    </div>
</div>
</div>
@endsection
