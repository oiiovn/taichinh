@extends('layouts.food')

@section('foodContent')
@php
    $inputClass = 'w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white';
    $labelClass = 'mb-1 block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
    $fmtQty = fn ($n) => rtrim(rtrim(number_format((float) $n, 6, '.', ','), '0'), '.');
@endphp
<div class="space-y-3 md:space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Công thức định lượng</h2>
            <p class="text-sm text-gray-500">{{ $product->ma_hang }} — {{ $product->ten_hang }}</p>
        </div>
        <a href="{{ route('food.san-pham') }}" class="rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700">← Sản phẩm</a>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">{{ session('error') }}</div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
        <h3 class="mb-2.5 text-sm font-semibold">Thêm / cập nhật dòng định lượng</h3>
        @if($materials->isEmpty())
            <p class="text-sm text-gray-500">Chưa có nguyên liệu. <a href="{{ route('food.nguyen-lieu') }}" class="text-brand-600 hover:underline">Tạo danh mục trước</a>.</p>
        @else
            <form action="{{ route('food.san-pham.cong-thuc.store', $product) }}" method="post" class="grid gap-2 sm:grid-cols-3">
                @csrf
                <div class="sm:col-span-2">
                    <label class="{{ $labelClass }}">Nguyên liệu / bao bì</label>
                    <select name="food_material_id" required class="{{ $inputClass }}">
                        <option value="">— Chọn —</option>
                        @foreach($materials as $mat)
                            <option value="{{ $mat->id }}">{{ $typeLabels[$mat->type] ?? '' }} · {{ $mat->name }} ({{ $mat->unit }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $labelClass }}">SL / 1 sp bán</label>
                    <input type="number" name="qty_per_unit" step="0.000001" min="0.000001" required placeholder="0.05" class="{{ $inputClass }}">
                </div>
                <div class="sm:col-span-3">
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Lưu định lượng</button>
                </div>
            </form>
        @endif
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-2.5 font-medium">Nguyên liệu</th>
                    <th class="px-3 py-2.5 font-medium">Loại</th>
                    <th class="px-3 py-2.5 font-medium">Định lượng / 1 sp</th>
                    <th class="px-3 py-2.5 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($product->recipes as $recipe)
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <td class="px-3 py-2 font-medium text-gray-900 dark:text-white">{{ $recipe->material?->name ?? '—' }}</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $typeLabels[$recipe->material?->type] ?? '—' }}</td>
                        <td class="px-3 py-2 tabular-nums">{{ $fmtQty($recipe->qty_per_unit) }} {{ $recipe->material?->unit }}</td>
                        <td class="px-3 py-2">
                            <form action="{{ route('food.san-pham.cong-thuc.destroy', [$product, $recipe]) }}" method="post" onsubmit="return confirm('Xóa dòng này?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline dark:text-red-400">Xóa</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-8 text-center text-gray-500">Chưa có dòng công thức.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
