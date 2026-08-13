@php
    $filterQuery = array_filter([
        'branch_id' => $branchId,
        'type' => $typeFilter ?: null,
        'q' => ($search ?? '') !== '' ? $search : null,
    ], fn ($v) => $v !== null && $v !== '');
    $filterInputClass = 'w-full appearance-none rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-100 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-brand-900/40';
@endphp
<form method="get" class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
    <div class="flex flex-col gap-3 lg:grid lg:grid-cols-[minmax(160px,0.9fr)_minmax(140px,0.7fr)_auto_minmax(200px,1.3fr)_auto] lg:items-end lg:gap-3">
        <div>
            <label class="{{ $labelClass }}">Chi nhánh</label>
            <select name="branch_id" class="{{ $filterInputClass }}">
                @foreach($branches as $b)
                    <option value="{{ $b->id }}" @selected((int) $branchId === (int) $b->id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="{{ $labelClass }}">Loại</label>
            <select name="type" class="{{ $filterInputClass }}">
                <option value="">Tất cả</option>
                @foreach($typeLabels as $k => $label)
                    <option value="{{ $k }}" @selected($typeFilter === $k)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex min-w-0 shrink-0 items-center rounded-xl bg-gray-100 p-1 dark:bg-gray-800">
            <a href="{{ route('food.nguyen-lieu', $filterQuery) }}"
                class="whitespace-nowrap rounded-lg px-3.5 py-2 text-sm font-semibold transition {{ ! $lowOnly ? 'bg-brand-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white' }}"
            >Tất cả</a>
            <a href="{{ route('food.nguyen-lieu', array_merge($filterQuery, ['low_only' => 1])) }}"
                class="whitespace-nowrap rounded-lg px-3.5 py-2 text-sm font-medium transition {{ $lowOnly ? 'bg-brand-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white' }}"
            >Chỉ dưới điểm đặt hàng</a>
        </div>
        <div>
            <label class="sr-only">Tìm kiếm</label>
            <div class="relative">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"/></svg>
                <input type="search" name="q" value="{{ $search ?? '' }}" placeholder="Tìm kiếm tên, mã hàng..." class="{{ $filterInputClass }} pl-10">
            </div>
        </div>
        <button type="submit" class="inline-flex w-full shrink-0 items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-700 lg:w-auto lg:min-w-[88px] lg:py-2.5">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z"/></svg>
            Lọc
        </button>
    </div>
    @if($lowOnly)
        <input type="hidden" name="low_only" value="1">
    @endif
</form>
