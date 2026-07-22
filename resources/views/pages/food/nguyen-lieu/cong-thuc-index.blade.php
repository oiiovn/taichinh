@extends('layouts.food')

@section('foodContent')
@php
    $inputClass = 'w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white';
    $labelClass = 'mb-1 block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
@endphp
<div class="space-y-3 md:space-y-5" x-data="{ addOpen: false }">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Công thức định lượng</h2>
            <p class="text-sm text-gray-500">Một công thức gán được cho nhiều sản phẩm</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('food.nguyen-lieu') }}" class="rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700">Nguyên liệu</a>
            <button type="button" @click="addOpen = !addOpen" class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white">+ Tạo công thức</button>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">{{ session('success') }}</div>
    @endif

    <div x-show="addOpen" x-cloak class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
        <form action="{{ route('food.cong-thuc.store') }}" method="post" class="grid gap-2 sm:grid-cols-2">
            @csrf
            <div>
                <label class="{{ $labelClass }}">Tên công thức *</label>
                <input type="text" name="name" required placeholder="VD: Set trà sữa chuẩn" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="{{ $labelClass }}">Ghi chú</label>
                <input type="text" name="note" maxlength="500" class="{{ $inputClass }}">
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Tạo</button>
            </div>
        </form>
    </div>

    <div class="space-y-2 md:hidden">
        @forelse($templates as $tpl)
            <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <a href="{{ route('food.cong-thuc.show', $tpl) }}" class="block">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $tpl->name }}</h3>
                    <p class="mt-1 text-xs text-gray-500">{{ $tpl->items_count }} NL · {{ $tpl->products_count }} sản phẩm</p>
                </a>
                <div class="mt-2 flex gap-3 border-t border-gray-100 pt-2 dark:border-gray-800">
                    <a href="{{ route('food.cong-thuc.show', $tpl) }}" class="text-xs font-medium text-brand-600 dark:text-brand-400">Mở</a>
                    <form action="{{ route('food.cong-thuc.duplicate', $tpl) }}" method="post">
                        @csrf
                        <button type="submit" class="text-xs font-medium text-gray-700 dark:text-gray-300">Sao chép</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 px-3 py-8 text-center text-sm text-gray-500 dark:border-gray-700">Chưa có công thức. Tạo mới rồi gán nhiều món.</div>
        @endforelse
    </div>

    <div class="hidden overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700 md:block">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-2.5 font-medium">Tên</th>
                    <th class="px-3 py-2.5 font-medium">Số NL</th>
                    <th class="px-3 py-2.5 font-medium">Số SP gắn</th>
                    <th class="px-3 py-2.5 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($templates as $tpl)
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <td class="px-3 py-2 font-medium text-gray-900 dark:text-white">{{ $tpl->name }}</td>
                        <td class="px-3 py-2 tabular-nums">{{ $tpl->items_count }}</td>
                        <td class="px-3 py-2 tabular-nums">{{ $tpl->products_count }}</td>
                        <td class="px-3 py-2">
                            <a href="{{ route('food.cong-thuc.show', $tpl) }}" class="mr-3 text-brand-600 hover:underline dark:text-brand-400">Mở</a>
                            <form action="{{ route('food.cong-thuc.duplicate', $tpl) }}" method="post" class="inline">
                                @csrf
                                <button type="submit" class="text-gray-600 hover:underline dark:text-gray-300">Sao chép</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-8 text-center text-gray-500">Chưa có công thức.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
