@php
    $oweItemsForProjection = $oweItemsForProjection ?? collect();
    $projectionMonths = (int) request()->input('projection_months', 12);
@endphp
<div class="rounded-xl border border-gray-200 bg-gray-50/50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
    <h4 class="mb-3 text-theme-sm font-semibold text-gray-800 dark:text-white">Scenario: Thử điều chỉnh</h4>
    <form method="get" action="{{ route('tai-chinh', ['tab' => 'chien-luoc']) }}" class="space-y-3" id="projection-scenario-form">
        <input type="hidden" name="tab" value="chien-luoc">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="mb-1 block text-theme-xs font-medium text-gray-600 dark:text-gray-400">Timeline</label>
                <select name="projection_months" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-theme-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="3" {{ $projectionMonths === 3 ? 'selected' : '' }}>3 tháng</option>
                    <option value="6" {{ $projectionMonths === 6 ? 'selected' : '' }}>6 tháng</option>
                    <option value="12" {{ $projectionMonths === 12 ? 'selected' : '' }}>12 tháng</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-theme-xs font-medium text-gray-600 dark:text-gray-400">Tăng thu (₫/tháng)</label>
                <input type="text" name="extra_income_per_month" value="{{ request()->input('extra_income_per_month') }}" placeholder="0" inputmode="numeric" data-format-vnd class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-theme-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            </div>
            <div>
                <label class="mb-1 block text-theme-xs font-medium text-gray-600 dark:text-gray-400">Giảm chi (%)</label>
                <input type="number" name="expense_reduction_pct" value="{{ request()->input('expense_reduction_pct') }}" placeholder="0" min="0" max="100" step="5" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-theme-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            </div>
            <div>
                <label class="mb-1 block text-theme-xs font-medium text-gray-600 dark:text-gray-400">Trả thêm (khoản vay)</label>
                <select name="extra_payment_loan_id" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-theme-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="">— Không —</option>
                    @foreach($oweItemsForProjection->where('source', 'linked') as $item)
                        <option value="{{ $item->entity->id ?? '' }}" {{ request()->input('extra_payment_loan_id') == ($item->entity->id ?? '') ? 'selected' : '' }}>{{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <div class="w-full sm:w-auto">
                <label class="mb-1 block text-theme-xs font-medium text-gray-600 dark:text-gray-400">Số tiền trả thêm (₫/tháng)</label>
                <input type="text" name="extra_payment_amount" value="{{ request()->input('extra_payment_amount') }}" placeholder="0" inputmode="numeric" data-format-vnd class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-theme-sm sm:w-40 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            </div>
            <button type="submit" class="mt-5 rounded-lg bg-brand-600 px-4 py-2 text-theme-sm font-medium text-white hover:bg-brand-700 dark:bg-brand-500 dark:hover:bg-brand-600 sm:mt-6">
                Tính lại
            </button>
        </div>
    </form>
</div>
