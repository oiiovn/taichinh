<form action="{{ route('food.nguyen-lieu.update-unit-cost', $material) }}" method="post" class="{{ $formClass ?? 'flex flex-wrap items-center gap-1' }}">
    @csrf
    @method('PATCH')
    <input type="hidden" name="branch_id" value="{{ $branchId }}">
    <input
        type="number"
        name="last_unit_cost"
        step="1"
        min="0"
        value="{{ $material->last_unit_cost }}"
        placeholder="Giá/đv"
        class="{{ $inputClass }}"
        title="Giá trên 1 {{ $material->unit }}"
    >
    <button type="submit" class="{{ $buttonClass ?? 'rounded bg-brand-600 px-2 py-1 text-xs font-medium text-white' }}">Lưu giá</button>
</form>
