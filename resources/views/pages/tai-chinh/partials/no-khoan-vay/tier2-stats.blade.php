@php
    $oweStats = $oweStats ?? ['total_principal' => 0, 'total_unpaid_interest' => 0, 'nearest_due' => null, 'nearest_due_name' => null, 'nearest_due_days' => null, 'avg_interest_rate_year' => 0];
    $receiveStats = $receiveStats ?? ['total_principal' => 0, 'total_unpaid_interest' => 0, 'nearest_due' => null, 'nearest_due_name' => null, 'nearest_due_days' => null, 'avg_interest_rate_year' => 0];
    $formatNearestDue = function ($stats) {
        if (empty($stats['nearest_due'])) return '—';
        $name = $stats['nearest_due_name'] ?? '';
        $days = $stats['nearest_due_days'] ?? null;
        if ($days !== null && $days >= 0) {
            $label = $days <= 30 ? 'Còn ' . $days . ' ngày' : 'Đáo hạn sau ' . $days . ' ngày';
            return $name !== '' ? $label . ' · ' . $name : $label;
        }
        return ($stats['nearest_due']->format('d/m/Y') ?? '') . ($name !== '' ? ' · ' . $name : '');
    };
@endphp
<div class="mt-8 flex flex-wrap gap-6">
    <div class="min-w-0 flex-1 rounded-xl border border-error-200 bg-error-50/50 p-4 shadow-theme-sm dark:border-gray-800 dark:bg-gray-dark dark:text-white" style="min-width: 200px;">
        <h3 class="text-theme-sm font-bold uppercase tracking-wider text-error-700 dark:text-error-400">Nợ (tôi đi vay)</h3>
        <dl class="mt-3 space-y-1.5 text-theme-sm">
            <div class="flex justify-between"><dt class="text-gray-600 dark:text-gray-400">Tổng dư nợ</dt><dd class="font-semibold tabular-nums text-gray-800 dark:text-white">{{ number_format($oweStats['total_principal']) }} ₫</dd></div>
            <div class="flex justify-between"><dt class="text-gray-600 dark:text-gray-400">Tổng lãi chưa trả</dt><dd class="font-semibold tabular-nums text-warning-600 dark:text-warning-400">{{ number_format($oweStats['total_unpaid_interest']) }} ₫</dd></div>
            <div class="flex justify-between"><dt class="text-gray-600 dark:text-gray-400">Gần đáo hạn</dt><dd class="font-medium text-gray-800 dark:text-white">{{ $formatNearestDue($oweStats) }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-600 dark:text-gray-400">Lãi suất TB (năm)</dt><dd class="font-semibold text-gray-800 dark:text-white">{{ $oweStats['avg_interest_rate_year'] }}%</dd></div>
        </dl>
    </div>
    <div class="min-w-0 flex-1 rounded-xl border border-success-200 bg-success-50/50 p-4 shadow-theme-sm dark:border-gray-800 dark:bg-gray-dark dark:text-white" style="min-width: 200px;">
        <h3 class="text-theme-sm font-bold uppercase tracking-wider text-success-700 dark:text-success-400">Cho vay (sẽ thu về)</h3>
        <dl class="mt-3 space-y-1.5 text-theme-sm">
            <div class="flex justify-between"><dt class="text-gray-600 dark:text-gray-400">Tổng dư nợ</dt><dd class="font-semibold tabular-nums text-gray-800 dark:text-white">{{ number_format($receiveStats['total_principal']) }} ₫</dd></div>
            <div class="flex justify-between"><dt class="text-gray-600 dark:text-gray-400">Tổng lãi chưa thu</dt><dd class="font-semibold tabular-nums text-warning-600 dark:text-warning-400">{{ number_format($receiveStats['total_unpaid_interest']) }} ₫</dd></div>
            <div class="flex justify-between"><dt class="text-gray-600 dark:text-gray-400">Gần đáo hạn</dt><dd class="font-medium text-gray-800 dark:text-white">{{ $formatNearestDue($receiveStats) }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-600 dark:text-gray-400">Lãi suất TB (năm)</dt><dd class="font-semibold text-gray-800 dark:text-white">{{ $receiveStats['avg_interest_rate_year'] }}%</dd></div>
        </dl>
    </div>
</div>
