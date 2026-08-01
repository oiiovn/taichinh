<form action="{{ route('food.nguyen-lieu.stock-adjust', $material) }}" method="post" class="{{ $formClass ?? 'flex flex-wrap items-center gap-1' }}">
    @csrf
    <input type="hidden" name="food_branch_id" value="{{ $branchId }}">
    <input
        type="number"
        name="stock_on_hand"
        step="0.0001"
        min="0"
        required
        value="{{ $stockQty }}"
        placeholder="Tồn"
        class="{{ $inputClass }}"
        title="Tồn kho chi nhánh hiện tại"
    >
    <button type="submit" class="{{ $buttonClass ?? 'rounded bg-gray-800 px-2 py-1 text-xs font-medium text-white dark:bg-gray-200 dark:text-gray-900' }}">Lưu tồn</button>
</form>
