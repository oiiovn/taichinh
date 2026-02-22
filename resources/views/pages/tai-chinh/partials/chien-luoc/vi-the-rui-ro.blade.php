@php
    $position = $position ?? ['net_leverage' => 0, 'risk_label' => 'Thấp', 'risk_color' => 'green'];
    $projection = $projection ?? null;
    $riskLabel = $projection['risk_label'] ?? $position['risk_label'] ?? 'Thấp';
    $riskColor = $projection['risk_color'] ?? $position['risk_color'] ?? 'green';
    $net = (float) ($position['net_leverage'] ?? 0);
    $netClass = $net > 0 ? 'text-success-600 dark:text-success-400' : ($net < 0 ? 'text-error-600 dark:text-error-400' : 'text-gray-600 dark:text-gray-400');
    $riskBg = match($riskColor) { 'red' => 'bg-error-500', 'yellow' => 'bg-warning-500', default => 'bg-success-500' };
@endphp
<section class="rounded-xl border border-gray-200 bg-gray-50/50 p-5 dark:border-gray-800 dark:bg-gray-900/50">
    <h3 class="mb-4 text-base font-semibold text-gray-800 dark:text-white">Vị thế & Rủi ro</h3>
    <div class="flex flex-wrap items-center gap-6">
        <div>
            <p class="text-theme-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Net leverage</p>
            <p class="mt-1 text-2xl font-bold tabular-nums {{ $netClass }}">{{ $net >= 0 ? '+' : '' }}{{ number_format($net) }} ₫</p>
        </div>
        <div class="h-10 w-px bg-gray-200 dark:bg-gray-700"></div>
        <div>
            <p class="text-theme-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Mức rủi ro</p>
            <p class="mt-1 flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full {{ $riskBg }}"></span>
                <span class="text-lg font-semibold text-gray-800 dark:text-white">{{ $riskLabel }}</span>
            </p>
        </div>
    </div>
</section>
