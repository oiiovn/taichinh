@extends('layouts.food')

@section('foodContent')
@php
    $inputClass = 'w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-100 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:ring-brand-900/40';
    $labelClass = 'mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
    $fmtQty = fn ($n) => rtrim(rtrim(number_format((float) $n, 4, '.', ','), '0'), '.');
    $branchId = $branch?->id;
    $totalRecords = $materials->count();
@endphp

<div class="space-y-5" x-data="{
    addOpen: {{ $errors->any() || old('name') ? 'true' : 'false' }},
    stockOpen: null,
    stockEdit: null,
    priceEdit: null,
    page: 1,
    perPage: 10,
    total: {{ $totalRecords }},
    get totalPages() { return Math.max(1, Math.ceil(this.total / this.perPage)); },
    rowVisible(i) { return i >= (this.page - 1) * this.perPage && i < this.page * this.perPage; },
    get rangeFrom() { return this.total === 0 ? 0 : (this.page - 1) * this.perPage + 1; },
    get rangeTo() { return Math.min(this.page * this.perPage, this.total); }
}">

    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-2xl">Nguyên liệu & bao bì</h1>
                <span class="hidden text-gray-400 sm:inline" title="Danh mục NL chung; tồn kho theo từng chi nhánh">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            @if($branch)
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kho chi nhánh: <span class="font-medium text-gray-800 dark:text-gray-200">{{ $branch->name }}</span></p>
            @endif
        </div>
        @if($branch)
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('food.nguyen-lieu.dat-hang', array_filter(['branch_id' => $branchId])) }}"
                    class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                    Gợi ý đặt hàng
                </a>
                <a href="{{ route('food.cong-thuc') }}"
                    class="inline-flex items-center gap-2 rounded-xl border border-brand-200 bg-brand-50 px-3 py-2 text-sm font-semibold text-brand-700 shadow-sm hover:bg-brand-100 dark:border-brand-800 dark:bg-brand-900/30 dark:text-brand-300">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                    Công thức
                </a>
                <button type="button" @click="addOpen = !addOpen"
                    class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                    Thêm
                </button>
            </div>
        @endif
    </div>

    @foreach(['success', 'error'] as $flash)
        @if(session($flash))
            <div @class([
                'rounded-xl border px-4 py-3 text-sm',
                'border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200' => $flash === 'success',
                'border-red-200 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200' => $flash === 'error',
            ])>{{ session($flash) }}</div>
        @endif
    @endforeach

    @if($branches->isEmpty())
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-5 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
            Cần có chi nhánh trước khi quản lý tồn NL.
            <a href="{{ route('food.chi-nhanh') }}" class="font-semibold underline">Tạo chi nhánh</a>
        </div>
    @else
        @include('pages.food.nguyen-lieu.partials.summary-cards')

        @include('pages.food.nguyen-lieu.partials.filters-bar')

        <div x-show="addOpen" x-cloak x-transition class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Thêm nguyên liệu / bao bì</h3>
            <form action="{{ route('food.nguyen-lieu.store') }}" method="post" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @csrf
                <input type="hidden" name="food_branch_id" value="{{ $branchId }}">
                <div>
                    <label class="{{ $labelClass }}">Tên *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Mã (tuỳ chọn)</label>
                    <input type="text" name="code" value="{{ old('code') }}" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Loại *</label>
                    <select name="type" required class="{{ $inputClass }}">
                        @foreach($typeLabels as $k => $label)
                            <option value="{{ $k }}" @selected(old('type', 'nguyen_lieu') === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Đơn vị *</label>
                    <input type="text" name="unit" value="{{ old('unit', 'cái') }}" required placeholder="kg, g, cái, hộp…" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Tồn đầu (CN đang chọn)</label>
                    <input type="number" name="stock_on_hand" step="0.0001" min="0" value="{{ old('stock_on_hand', 0) }}" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Điểm đặt hàng (CN này)</label>
                    <input type="number" name="reorder_point" step="0.0001" min="0" value="{{ old('reorder_point', 0) }}" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">SL mỗi lần đặt (lô)</label>
                    <input type="number" name="order_qty" step="0.0001" min="0" value="{{ old('order_qty') }}" placeholder="vd 1000 — để trống = theo gợi ý" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Giá/đv (đ) — nếu có tồn đầu thì nhập tổng tiền lô</label>
                    <input type="number" name="last_unit_cost" step="1" min="0" value="{{ old('last_unit_cost') }}" class="{{ $inputClass }}">
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <label class="{{ $labelClass }}">Ghi chú</label>
                    <input type="text" name="note" value="{{ old('note') }}" maxlength="500" class="{{ $inputClass }}">
                </div>
                <div class="sm:col-span-2 lg:col-span-3 flex flex-wrap gap-2">
                    <button type="submit" class="rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">Lưu</button>
                    <button type="button" @click="addOpen = false" class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-300">Huỷ</button>
                </div>
            </form>
        </div>

        @include('pages.food.nguyen-lieu.partials.material-cards-mobile')

        @include('pages.food.nguyen-lieu.partials.material-table', [
            'materials' => $materials,
            'branchId' => $branchId,
            'typeLabels' => $typeLabels,
            'materialUsages' => $materialUsages ?? [],
            'fmtQty' => $fmtQty,
            'inputClass' => $inputClass,
        ])
    @endif
</div>
@endsection
