@extends('layouts.food')

@section('foodContent')
@php
    $inputClass = 'w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white';
    $labelClass = 'mb-1 block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
    $fmtQty = fn ($n) => rtrim(rtrim(number_format((float) $n, 6, '.', ','), '0'), '.');
    $sauceNameKeywords = ['Sốt Bò', 'Sốt Me', 'Sốt Bơ', 'Muối Tắc'];
    $highlightSauceName = function (string $name) use ($sauceNameKeywords): string {
        $pattern = '/('.implode('|', array_map(static fn ($k) => preg_quote($k, '/'), $sauceNameKeywords)).')/iu';
        $parts = preg_split($pattern, $name, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return e($name);
        }
        $html = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $isSauce = collect($sauceNameKeywords)->contains(
                fn ($k) => mb_strtolower($k) === mb_strtolower($part)
            );
            $html .= $isSauce
                ? '<span class="text-orange-500 dark:text-orange-400">'.e($part).'</span>'
                : e($part);
        }

        return $html;
    };
@endphp
<div class="space-y-3 md:space-y-5" x-data="{ productFilter: '' }">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{!! $highlightSauceName($template->name) !!}</h2>
            <p class="text-sm text-gray-500">Định lượng dùng chung · gán nhiều sản phẩm</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('food.cong-thuc') }}" class="rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700">← Danh sách CT</a>
            <form action="{{ route('food.cong-thuc.duplicate', $template) }}" method="post">
                @csrf
                <button type="submit" class="rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm font-medium text-brand-700 hover:bg-brand-100 dark:border-brand-800 dark:bg-brand-900/30 dark:text-brand-300">Sao chép</button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">{{ session('success') }}</div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
        <form action="{{ route('food.cong-thuc.update', $template) }}" method="post" class="grid gap-2 sm:grid-cols-2">
            @csrf @method('PUT')
            <div>
                <label class="{{ $labelClass }}">Tên công thức</label>
                <input type="text" name="name" value="{{ $template->name }}" required class="{{ $inputClass }}">
            </div>
            <div>
                <label class="{{ $labelClass }}">Ghi chú</label>
                <input type="text" name="note" value="{{ $template->note }}" maxlength="500" class="{{ $inputClass }}">
            </div>
            <div class="flex flex-wrap gap-2 sm:col-span-2">
                <button type="submit" class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white">Lưu tên</button>
                <button type="submit" form="delete-tpl" class="rounded-lg border border-red-200 px-3 py-2 text-sm text-red-600 dark:border-red-900 dark:text-red-400" onclick="return confirm('Xóa công thức này? SP sẽ bỏ gán.');">Xóa CT</button>
            </div>
        </form>
        <form id="delete-tpl" action="{{ route('food.cong-thuc.destroy', $template) }}" method="post" class="hidden">@csrf @method('DELETE')</form>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900" x-data="{ itemType: 'material' }">
        <h3 class="mb-2.5 text-sm font-semibold">Định lượng (NL / bao bì hoặc công thức con)</h3>
        @if($errors->any())
            <div class="mb-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-300">{{ $errors->first() }}</div>
        @endif
        <form action="{{ route('food.cong-thuc.items.store', $template) }}" method="post" class="mb-3 grid gap-2 sm:grid-cols-3">
            @csrf
            <div class="sm:col-span-3 flex flex-wrap gap-3 text-sm">
                <label class="inline-flex items-center gap-2">
                    <input type="radio" name="item_type" value="material" x-model="itemType" class="border-gray-300" @checked(old('item_type', 'material') === 'material')>
                    Nguyên liệu / bao bì
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="radio" name="item_type" value="recipe" x-model="itemType" class="border-gray-300" @checked(old('item_type') === 'recipe')>
                    Công thức khác
                </label>
            </div>
            <div class="sm:col-span-2" x-show="itemType === 'material'" x-cloak>
                <label class="{{ $labelClass }}">Nguyên liệu</label>
                <select name="food_material_id" class="{{ $inputClass }}" :required="itemType === 'material'">
                    <option value="">— Chọn —</option>
                    @foreach($materials as $mat)
                        <option value="{{ $mat->id }}" @selected((int) old('food_material_id') === (int) $mat->id)>{{ $typeLabels[$mat->type] ?? '' }} · {{ $mat->name }} ({{ $mat->unit }})</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2" x-show="itemType === 'recipe'" x-cloak>
                <label class="{{ $labelClass }}">Công thức con</label>
                <select name="child_template_id" class="{{ $inputClass }}" :required="itemType === 'recipe'">
                    <option value="">— Chọn CT —</option>
                    @foreach($otherTemplates ?? [] as $ot)
                        <option value="{{ $ot->id }}" @selected((int) old('child_template_id') === (int) $ot->id)>{{ $ot->name }}</option>
                    @endforeach
                </select>
                @if(($otherTemplates ?? collect())->isEmpty())
                    <p class="mt-1 text-xs text-gray-500">Cần có CT khác để gắn (vd: Sốt, nước sốt…).</p>
                @endif
            </div>
            <div>
                <label class="{{ $labelClass }}">SL / 1 sp</label>
                <input type="number" name="qty_per_unit" step="0.000001" min="0.000001" required value="{{ old('qty_per_unit') }}" class="{{ $inputClass }}">
            </div>
            <div class="sm:col-span-3">
                <button type="submit" class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white">Thêm / cập nhật dòng</button>
            </div>
        </form>
        <div class="overflow-x-auto rounded-lg border border-gray-100 dark:border-gray-800">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800/80">
                    <tr>
                        <th class="px-3 py-2 font-medium">Loại</th>
                        <th class="px-3 py-2 font-medium">Thành phần</th>
                        <th class="px-3 py-2 font-medium">Định lượng</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($template->items as $item)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="px-3 py-2">
                                @if($item->isRecipeLine())
                                    <span class="rounded bg-violet-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">CT</span>
                                @else
                                    <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-gray-600 dark:bg-gray-800 dark:text-gray-300">NL</span>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                @if($item->isRecipeLine())
                                    <a href="{{ route('food.cong-thuc.show', $item->child_template_id) }}" class="font-medium text-brand-600 hover:underline dark:text-brand-400">
                                        {{ $item->childTemplate?->name ?? 'CT #'.$item->child_template_id }}
                                    </a>
                                @else
                                    {{ $item->material?->name }}
                                    <span class="text-xs text-gray-500">({{ $typeLabels[$item->material?->type] ?? '' }})</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 tabular-nums">
                                {{ $fmtQty($item->qty_per_unit) }}
                                @if($item->isRecipeLine())
                                    × CT
                                @else
                                    {{ $item->material?->unit }}
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <form action="{{ route('food.cong-thuc.items.destroy', [$template, $item]) }}" method="post" onsubmit="return confirm('Xóa dòng?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline dark:text-red-400">Xóa</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-6 text-center text-gray-500">Chưa có dòng định lượng.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(!empty($bomPreview))
            <div class="mt-3 rounded-lg border border-dashed border-brand-200 bg-brand-50/50 p-3 dark:border-brand-900 dark:bg-brand-950/20">
                <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-brand-700 dark:text-brand-300">Quy ra NL gốc / 1 sp (đã bung CT lồng)</p>
                <ul class="max-h-40 space-y-1 overflow-y-auto text-sm">
                    @foreach($bomPreview as $row)
                        <li class="flex justify-between gap-2">
                            <span>{{ $row['name'] }} <span class="text-xs text-gray-500">({{ $typeLabels[$row['type']] ?? '' }})</span></span>
                            <span class="tabular-nums font-medium">{{ $fmtQty($row['qty']) }} {{ $row['unit'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
        <h3 class="mb-1 text-sm font-semibold">Gán sản phẩm dùng công thức này</h3>
        <p class="mb-2.5 text-xs text-gray-500">Chọn nhiều món — tất cả sẽ dùng chung định lượng phía trên.</p>
        <form action="{{ route('food.cong-thuc.products.sync', $template) }}" method="post">
            @csrf
            <input type="search" x-model="productFilter" placeholder="Lọc mã / tên…" class="mb-2 {{ $inputClass }}">
            <div class="mb-3 max-h-72 space-y-1 overflow-y-auto rounded-lg border border-gray-100 p-2 dark:border-gray-800">
                @forelse($products as $p)
                    <label class="flex items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-800/80"
                        x-show="!productFilter || '{{ strtolower(addslashes($p->ma_hang.' '.$p->ten_hang)) }}'.includes(productFilter.toLowerCase())">
                        <input type="checkbox" name="product_ids[]" value="{{ $p->id }}" @checked(in_array($p->id, $assignedIds, true)) class="rounded border-gray-300">
                        <span class="min-w-0 truncate">
                            <span class="font-medium text-gray-900 dark:text-white">{{ $p->ma_hang }}</span>
                            <span class="text-gray-600 dark:text-gray-400"> — {{ $p->ten_hang }}</span>
                            @if($p->food_recipe_template_id && (int) $p->food_recipe_template_id !== (int) $template->id)
                                <span class="text-[10px] text-amber-600">(đang gắn CT khác)</span>
                            @endif
                        </span>
                    </label>
                @empty
                    <p class="px-2 py-4 text-center text-sm text-gray-500">Chưa có sản phẩm.</p>
                @endforelse
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Lưu danh sách sản phẩm</button>
        </form>
    </div>
</div>
@endsection
