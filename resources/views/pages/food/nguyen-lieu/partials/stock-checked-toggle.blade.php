@php
    $checked = $material->isStockChecked();
@endphp
<form action="{{ route('food.nguyen-lieu.kiem-ton', $material) }}" method="post" class="inline-flex">
    @csrf
    @method('PATCH')
    <input type="hidden" name="food_branch_id" value="{{ $branchId }}">
    @if($typeFilter)
        <input type="hidden" name="type" value="{{ $typeFilter }}">
    @endif
    @if(($search ?? '') !== '')
        <input type="hidden" name="q" value="{{ $search }}">
    @endif
    @if($lowOnly ?? false)
        <input type="hidden" name="low_only" value="1">
    @endif
    <button type="submit" role="switch" aria-checked="{{ $checked ? 'true' : 'false' }}"
        title="{{ $checked ? 'Bỏ đánh dấu đã kiểm tồn' : 'Đánh dấu đã kiểm tồn — ưu tiên lên đầu' }}"
        class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition {{ $checked ? 'bg-brand-600' : 'bg-gray-300 dark:bg-gray-600' }}">
        <span class="inline-block h-4 w-4 rounded-full bg-white shadow transition {{ $checked ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
        <span class="sr-only">{{ $checked ? 'Đã kiểm tồn' : 'Chưa kiểm tồn' }}</span>
    </button>
</form>
