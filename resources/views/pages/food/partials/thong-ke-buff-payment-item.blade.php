<div class="rounded-xl border border-gray-200 bg-gray-50/80 px-3 py-2.5 dark:border-gray-700 dark:bg-gray-800/50">
    <div class="flex items-center justify-between gap-3 border-b border-gray-200/80 pb-2 dark:border-gray-600/80">
        <span class="min-w-0 text-sm font-medium text-gray-900 dark:text-white">{{ $p->paid_at?->format('d/m/Y H:i') ?? '—' }}</span>
        <span class="shrink-0 text-sm font-semibold tabular-nums text-orange-600 dark:text-orange-400">{{ $fmt($p->amount) }} đ</span>
    </div>
    <div class="mt-2 space-y-2 text-xs text-gray-700 dark:text-gray-300">
        <div class="font-medium text-gray-900 dark:text-gray-100">
            {{
                $p->payment_method === 'cash'
                    ? 'Tiền mặt'
                    : ($p->payment_method === 'bank' ? 'Chuyển khoản' : strtoupper((string) $p->payment_method))
            }}@if($p->note)<span class="text-gray-400 dark:text-gray-500"> · </span>{{ $p->note }}@endif
        </div>
    </div>
</div>
