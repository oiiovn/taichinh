@extends('layouts.food')

@section('foodContent')
@php
    $productsJson = $products->map(fn ($p) => [
        'id' => $p->id,
        'ma_hang' => $p->ma_hang,
        'ten_hang' => $p->ten_hang ?? '',
        'recipe_name' => $p->recipeTemplate?->name,
        'recipe_id' => $p->food_recipe_template_id,
        'recipe_url' => $p->food_recipe_template_id && \Illuminate\Support\Facades\Route::has('food.cong-thuc.show')
            ? route('food.cong-thuc.show', $p->food_recipe_template_id)
            : null,
    ])->values()->toJson();
@endphp
<div class="space-y-4 md:space-y-5" x-data="{ q: '', items: {{ $productsJson }} }">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Món</h2>
            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Danh sách món và công thức đang gán</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if(\Illuminate\Support\Facades\Route::has('food.san-pham'))
                <a href="{{ route('food.san-pham') }}" class="rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700">Sản phẩm</a>
            @endif
            @if(\Illuminate\Support\Facades\Route::has('food.cong-thuc'))
                <a href="{{ route('food.cong-thuc') }}" class="rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700">Công thức</a>
            @endif
        </div>
    </div>

    <input type="search" x-model="q" placeholder="Lọc tên món, mã hàng, công thức…"
        class="w-full max-w-md rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <template x-for="p in items.filter(i => !q || (i.ten_hang + ' ' + i.ma_hang + ' ' + (i.recipe_name || '')).toLowerCase().includes(q.toLowerCase()))" :key="p.id">
            <article class="flex flex-col rounded-xl border border-gray-200 bg-white p-3 shadow-sm transition hover:border-brand-200 hover:shadow-md dark:border-gray-700 dark:bg-gray-900 dark:hover:border-brand-800">
                <h3 class="text-sm font-semibold leading-snug text-gray-950 dark:text-white" x-text="p.ten_hang || '—'"></h3>
                <p class="mt-1 font-mono text-xs text-gray-500 dark:text-gray-400">
                    <span class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Mã hàng</span><br>
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200" x-text="p.ma_hang"></span>
                </p>
                <div class="mt-3 flex-1 border-t border-gray-100 pt-2.5 dark:border-gray-800">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Công thức</p>
                    <template x-if="p.recipe_name">
                        <a :href="p.recipe_url" class="mt-0.5 block text-sm font-medium text-brand-600 hover:underline dark:text-brand-400" x-text="p.recipe_name"></a>
                    </template>
                    <template x-if="!p.recipe_name">
                        <p class="mt-0.5 text-sm text-amber-600 dark:text-amber-400">Chưa gán công thức</p>
                    </template>
                </div>
            </article>
        </template>
    </div>

    @if($products->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 px-4 py-10 text-center text-sm text-gray-500 dark:border-gray-700">
            Chưa có món. Thêm sản phẩm rồi gán công thức trong menu Công thức.
        </div>
    @endif
</div>
@endsection
