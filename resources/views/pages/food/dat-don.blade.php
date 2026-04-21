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

<div class="space-y-6">
    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">{{ session('error') }}</div>
    @endif

    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Đặt đơn ShopeeFood</h2>

    <form method="POST" action="{{ route('food.dat-don.store') }}" x-data="{ cooldown: {{ $cooldownRemaining }} }" x-init="if (cooldown > 0) { const t = setInterval(() => { cooldown = Math.max(0, cooldown - 1); if (cooldown === 0) clearInterval(t); }, 1000); }" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        @csrf
        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Ngày đặt *</label>
                <input type="date" name="order_date" value="{{ $defaultOrderDate }}" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Chi nhánh *</label>
                <select name="food_branch_id" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                    <option value="">Chọn chi nhánh</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) $defaultBranchId === (int) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Mã đơn *</label>
                <input type="text" value="Tự sinh khi tạo đơn" readonly class="w-full cursor-not-allowed rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Shopeefood *</label>
                <select name="customer_name" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                    <option value="">Chọn tên Shopeefood</option>
                    @foreach(($customerOptions ?? []) as $name)
                        <option value="{{ $name }}" @selected($defaultCustomerName === $name)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Sản phẩm *</label>
                <select name="product_name" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                    <option value="Quán Ship Bù" @selected($defaultProduct === 'Quán Ship Bù')>Quán Ship Bù</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Thu nhập *</label>
                <input type="text" value="10.000" readonly class="w-full cursor-not-allowed rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300">
            </div>
        </div>
        <p class="mt-1 text-xs text-amber-600 dark:text-amber-400" x-show="cooldown > 0" x-cloak>Vui lòng chờ <span x-text="cooldown"></span> giây để tạo đơn tiếp theo.</p>
        <div class="mt-3">
            <button type="submit" :disabled="cooldown > 0" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60">Tạo đơn</button>
        </div>
    </form>

    <form method="GET" action="{{ route('food.dat-don') }}" class="flex flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Từ ngày</label>
            <input type="date" name="from_date" value="{{ $from->format('Y-m-d') }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Đến ngày</label>
            <input type="date" name="to_date" value="{{ $to->format('Y-m-d') }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
        </div>
        <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-700">Lọc</button>
    </form>

    <div class="space-y-3">
        @forelse($orders as $o)
            @php
                $branchName = trim((string) ($o->branch?->name ?? '—'));
                $branchBadgeStyle = $branchBadgeStyles[abs(crc32(mb_strtolower($branchName))) % count($branchBadgeStyles)];
            @endphp
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
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
            <p class="rounded-xl border border-gray-200 bg-white px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Chưa có đơn nào trong kỳ lọc.</p>
        @endforelse
    </div>
</div>
@endsection
