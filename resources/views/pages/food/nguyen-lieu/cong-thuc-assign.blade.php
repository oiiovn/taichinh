@extends('layouts.food')

@section('foodContent')
@php
    $inputClass = 'w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white';
    $labelClass = 'mb-1 block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
@endphp
<div class="space-y-3 md:space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Gán công thức</h2>
            <p class="text-sm text-gray-500">{{ $product->ma_hang }} — {{ $product->ten_hang }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('food.cong-thuc') }}" class="rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700">Quản lý CT</a>
            <a href="{{ route('food.san-pham') }}" class="rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700">← Sản phẩm</a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if(session('info'))
        <div class="rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-200">{{ session('info') }}</div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
        <p class="mb-2 text-sm text-gray-600 dark:text-gray-400">
            Hiện tại:
            @if($product->recipeTemplate)
                <a href="{{ route('food.cong-thuc.show', $product->recipeTemplate) }}" class="font-semibold text-brand-600 hover:underline">{{ $product->recipeTemplate->name }}</a>
            @else
                <span class="font-medium text-gray-500">Chưa gán công thức</span>
            @endif
        </p>
        <form action="{{ route('food.san-pham.cong-thuc.assign', $product) }}" method="post" class="grid gap-2 sm:grid-cols-2">
            @csrf
            <div>
                <label class="{{ $labelClass }}">Chọn công thức mẫu</label>
                <select name="food_recipe_template_id" class="{{ $inputClass }}">
                    <option value="">— Không dùng —</option>
                    @foreach($templates as $tpl)
                        <option value="{{ $tpl->id }}" @selected((int) $product->food_recipe_template_id === (int) $tpl->id)>
                            {{ $tpl->name }} ({{ $tpl->items_count }} NL · {{ $tpl->products_count }} SP)
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Gán</button>
            </div>
        </form>
        @if($templates->isEmpty())
            <p class="mt-2 text-sm text-gray-500">Chưa có công thức mẫu. <a href="{{ route('food.cong-thuc') }}" class="text-brand-600 hover:underline">Tạo công thức</a> rồi quay lại.</p>
        @endif
    </div>
</div>
@endsection
