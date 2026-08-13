<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <p class="text-3xl font-bold tabular-nums text-brand-600 dark:text-brand-400">{{ number_format($stats['total'] ?? 0) }}</p>
        <p class="mt-1 text-sm font-medium text-gray-600 dark:text-gray-400">Tổng mặt hàng</p>
    </div>
    <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <p class="text-3xl font-bold tabular-nums text-red-600 dark:text-red-400">{{ number_format($stats['below_reorder'] ?? 0) }}</p>
        <p class="mt-1 text-sm font-medium text-gray-600 dark:text-gray-400">Dưới điểm đặt hàng</p>
    </div>
    <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <p class="text-3xl font-bold tabular-nums text-amber-500 dark:text-amber-400">{{ number_format($stats['low_stock'] ?? 0) }}</p>
        <p class="mt-1 text-sm font-medium text-gray-600 dark:text-gray-400">Sắp hết hàng</p>
    </div>
    <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <p class="text-3xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{{ number_format($stats['total_value'] ?? 0, 0, ',', '.') }}</p>
        <p class="mt-1 text-sm font-medium text-gray-600 dark:text-gray-400">Tổng giá trị tồn kho (VND)</p>
    </div>
</div>
