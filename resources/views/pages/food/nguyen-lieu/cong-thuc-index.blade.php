@extends('layouts.food')

@section('foodContent')
@php
    $inputClass = 'w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white';
    $labelClass = 'mb-1 block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
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
    $fmtCost = fn ($n) => \App\Helpers\BaoCaoHelper::formatGiaVonNguyen($n);
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

    <form method="get" action="{{ route('food.cong-thuc') }}" class="flex flex-wrap items-center gap-2 rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900" x-data="{ q: @js($search ?? '') }">
        <div class="relative min-w-0 flex-1">
            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </span>
            <input
                type="search"
                name="q"
                x-model="q"
                value="{{ $search ?? '' }}"
                placeholder="Tìm theo tên công thức..."
                class="{{ $inputClass }} pl-9"
                autocomplete="off"
            >
        </div>
        <button type="submit" class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700">Tìm</button>
        <a
            href="{{ route('food.cong-thuc', ['clear_search' => 1]) }}"
            class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
            x-show="q.length > 0"
            x-cloak
        >Xóa</a>
    </form>

    @if(($search ?? '') !== '')
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Đang lọc: <span class="font-medium text-gray-800 dark:text-gray-200">“{{ $search }}”</span>
            · {{ $templates->count() }} kết quả
            · <a href="{{ route('food.cong-thuc', ['clear_search' => 1]) }}" class="font-medium text-brand-600 hover:underline dark:text-brand-400">Bỏ lọc</a>
        </p>
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
            <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800/50">
                <a href="{{ route('food.cong-thuc.show', $tpl) }}" class="block">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{!! $highlightSauceName($tpl->name) !!}</h3>
                    <p class="mt-1 text-xs text-gray-500">{{ $tpl->items_count }} NL · {{ $tpl->products_count }} sản phẩm</p>
                    @php $cost = $recipeCosts[$tpl->id] ?? null; @endphp
                    @if($cost && ($cost['total'] > 0 || ($cost['missing_price_count'] ?? 0) > 0))
                        <p class="mt-1 text-xs font-semibold tabular-nums text-emerald-700 dark:text-emerald-300">
                            Giá vốn CT: {{ $fmtCost($cost['total']) }} đ
                            @if(($cost['missing_price_count'] ?? 0) > 0)
                                <span class="font-normal text-amber-600 dark:text-amber-400">({{ $cost['missing_price_count'] }} NL chưa có giá)</span>
                            @endif
                        </p>
                    @endif
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
            <div class="rounded-xl border border-dashed border-gray-300 px-3 py-8 text-center text-sm text-gray-500 dark:border-gray-700">
                @if(($search ?? '') !== '')
                    Không tìm thấy công thức khớp “{{ $search }}”.
                    <a href="{{ route('food.cong-thuc', ['clear_search' => 1]) }}" class="mt-2 block font-medium text-brand-600 hover:underline">Xóa bộ lọc</a>
                @else
                    Chưa có công thức. Tạo mới rồi gán nhiều món.
                @endif
            </div>
        @endforelse
    </div>

    <div class="hidden overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700 md:block">
        <table class="cong-thuc-table w-full text-left text-sm">
            <thead class="border-b border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-2.5 font-medium">Tên</th>
                    <th class="px-3 py-2.5 font-medium">Số NL</th>
                    <th class="px-3 py-2.5 font-medium">Số SP gắn</th>
                    <th class="px-3 py-2.5 font-medium text-right">Giá vốn CT</th>
                    <th class="px-3 py-2.5 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($templates as $tpl)
                    <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-100 dark:hover:bg-gray-800/50">
                        <td class="px-3 py-2 font-medium text-gray-900 dark:text-white">{!! $highlightSauceName($tpl->name) !!}</td>
                        <td class="px-3 py-2 tabular-nums">{{ $tpl->items_count }}</td>
                        <td class="px-3 py-2 tabular-nums">{{ $tpl->products_count }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">
                            @php $cost = $recipeCosts[$tpl->id] ?? null; @endphp
                            @if($cost && $cost['total'] > 0)
                                <span class="font-semibold text-emerald-700 dark:text-emerald-300">{{ $fmtCost($cost['total']) }} đ</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                            @if($cost && ($cost['missing_price_count'] ?? 0) > 0)
                                <div class="text-[10px] text-amber-600 dark:text-amber-400">{{ $cost['missing_price_count'] }} NL thiếu giá</div>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            <a href="{{ route('food.cong-thuc.show', $tpl) }}" class="mr-3 text-brand-600 hover:underline dark:text-brand-400">Mở</a>
                            <form action="{{ route('food.cong-thuc.duplicate', $tpl) }}" method="post" class="inline">
                                @csrf
                                <button type="submit" class="text-gray-600 hover:underline dark:text-gray-300">Sao chép</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-8 text-center text-gray-500">
                        @if(($search ?? '') !== '')
                            Không tìm thấy công thức khớp “{{ $search }}”.
                            <a href="{{ route('food.cong-thuc', ['clear_search' => 1]) }}" class="ml-1 font-medium text-brand-600 hover:underline">Xóa bộ lọc</a>
                        @else
                            Chưa có công thức.
                        @endif
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<style>
    .cong-thuc-table tbody tr:hover td {
        background-color: rgb(243 244 246);
    }
    .dark .cong-thuc-table tbody tr:hover td {
        background-color: rgb(31 41 55 / 0.7);
    }
</style>
@endsection
