@php
    $opt = $projectionOptimization ?? null;
    $priorityMode = $opt['priority_mode'] ?? null;
    $modeKey = $priorityMode['key'] ?? null;
@endphp
@if($opt)
<section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <h3 class="text-base font-semibold text-gray-800 dark:text-white">🎯 Đề xuất tối ưu</h3>
        @if($priorityMode)
            @php
                $badgeClass = match($modeKey) {
                    'crisis' => 'bg-error-100 text-error-800 dark:bg-error-900/40 dark:text-error-300',
                    'defensive' => 'bg-warning-100 text-warning-800 dark:bg-warning-900/40 dark:text-warning-300',
                    'optimization' => 'bg-brand-100 text-brand-800 dark:bg-brand-900/40 dark:text-brand-300',
                    'growth' => 'bg-success-100 text-success-800 dark:bg-success-900/40 dark:text-success-300',
                    default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                };
            @endphp
            <span class="rounded-full px-2.5 py-0.5 text-theme-xs font-medium {{ $badgeClass }}">{{ $priorityMode['label'] }}</span>
        @endif
    </div>

    @if($opt['survival_mode'] && !empty($opt['min_monthly_adjustment_vnd']))
        <div class="mb-4 rounded-lg border-2 border-error-300 bg-error-50 p-4 dark:border-error-700 dark:bg-error-900/30">
            <p class="font-semibold text-error-800 dark:text-error-200">⚠ Chế độ khủng hoảng</p>
            @if(($opt['survival_mode_reason'] ?? '') === 'no_income')
                <p class="mt-1 text-theme-sm text-error-700 dark:text-error-300">Bạn đang không có nguồn thu (hoặc thu rất thấp). Với cấu trúc hiện tại, cần tối thiểu <strong>{{ number_format((int) $opt['min_monthly_adjustment_vnd']) }} ₫/tháng</strong> dòng tiền vào để cân bằng.</p>
            @else
                <p class="mt-1 text-theme-sm text-error-700 dark:text-error-300">Cần giảm chi tối thiểu <strong>{{ number_format((int) $opt['min_monthly_adjustment_vnd']) }} ₫/tháng</strong> hoặc tăng thu tương đương để không vỡ dòng tiền.</p>
            @endif
        </div>
    @endif

    @if(!empty($opt['survival_horizon_message']))
        <div class="mb-4 rounded-lg border border-warning-200 bg-warning-50 p-3 dark:border-warning-800 dark:bg-warning-900/30">
            <p class="text-theme-sm text-warning-800 dark:text-warning-200">{{ $opt['survival_horizon_message'] }}</p>
            @if(isset($opt['survival_horizon_months']) && $opt['survival_horizon_months'] !== null)
                <p class="mt-1 text-theme-xs text-warning-700 dark:text-warning-300">Runway: <strong>{{ $opt['survival_horizon_months'] }}</strong> tháng.</p>
            @endif
        </div>
    @endif

    @if(!empty($opt['optimal_plan_message']))
        <div class="mb-4 rounded-lg border border-brand-200 bg-brand-50 p-3 dark:border-brand-800 dark:bg-brand-900/30">
            <p class="text-theme-sm font-medium text-brand-800 dark:text-brand-200">Phương án tối ưu nhất</p>
            <p class="mt-1 text-theme-sm text-brand-700 dark:text-brand-300">{{ $opt['optimal_plan_message'] }}</p>
        </div>
    @endif

    <ul class="space-y-2 text-theme-sm text-gray-700 dark:text-gray-300">
        @if(isset($opt['min_expense_reduction_pct']) && $opt['min_expense_reduction_pct'] !== null && $opt['min_expense_reduction_pct'] > 0)
            <li>→ Giảm chi ít nhất <strong>{{ (int) $opt['min_expense_reduction_pct'] }}%</strong> để không âm tiền trong kỳ dự báo.</li>
        @endif
        @if(isset($opt['min_extra_income_per_month']) && $opt['min_extra_income_per_month'] !== null && $opt['min_extra_income_per_month'] > 0)
            <li>→ Hoặc tăng thu ít nhất <strong>{{ number_format((int) $opt['min_extra_income_per_month']) }} ₫/tháng</strong>.</li>
        @endif
        @if(isset($opt['months_to_break_even']) && $opt['months_to_break_even'] !== null)
            <li>→ Với mức hiện tại, bạn có thể thoát âm từ <strong>tháng thứ {{ $opt['months_to_break_even'] }}</strong>.</li>
        @endif
        @if(!empty($opt['suggested_loan']['name']))
            @php $reason = $opt['suggested_loan']['reason'] ?? 'highest_interest'; @endphp
            @if($reason === 'nearest_due')
                <li>→ Nên ưu tiên trả khoản <strong>«{{ $opt['suggested_loan']['name'] }}»</strong> (kỳ hạn gần nhất) trước.</li>
            @elseif($reason === 'largest_balance')
                <li>→ Nên ưu tiên trả khoản <strong>«{{ $opt['suggested_loan']['name'] }}»</strong> (số dư lớn nhất) trước để giảm áp lực dòng tiền.</li>
            @else
                <li>→ Nên ưu tiên trả khoản <strong>«{{ $opt['suggested_loan']['name'] }}»</strong> (lãi cao nhất) trước để giảm tổng lãi.</li>
            @endif
        @endif
    </ul>

    @if(empty($opt['min_expense_reduction_pct']) && empty($opt['min_extra_income_per_month']) && empty($opt['months_to_break_even']) && empty($opt['suggested_loan']['name']) && !$opt['survival_mode'] && empty($opt['survival_horizon_message']) && empty($opt['optimal_plan_message']))
        <p class="text-theme-sm text-gray-500 dark:text-gray-400">Dòng tiền dự báo ổn. Duy trì mức thu chi hiện tại và ưu tiên trả nợ lãi cao trước.</p>
    @endif
</section>
@endif
