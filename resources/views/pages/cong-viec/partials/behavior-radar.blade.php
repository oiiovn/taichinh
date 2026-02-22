@if(isset($behaviorRadar) && $behaviorRadar)
<div class="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg border border-gray-200 bg-gray-50/80 px-3 py-2 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-400">
    @if(isset($behaviorRadar['trust_global']) && $behaviorRadar['trust_global'] !== null)
        <span>Trust: <strong>{{ number_format($behaviorRadar['trust_global'] * 100, 0) }}%</strong></span>
    @endif
    <span>Mode: {{ $behaviorRadar['mode'] === 'micro_goal' ? 'Mục tiêu nhỏ' : ($behaviorRadar['mode'] === 'reduced_reminder' ? 'Giảm nhắc' : 'Bình thường') }}</span>
    @if(isset($behaviorRadar['cli']) && $behaviorRadar['cli'] !== null)
        <span>CLI: {{ $behaviorRadar['cli'] > 0.7 ? 'Ổn định' : ($behaviorRadar['cli'] > 0.4 ? 'Bình thường' : 'Quá tải') }}</span>
    @endif
    @if(isset($behaviorRadar['projection_60d']) && $behaviorRadar['projection_60d'] !== null)
        <span>60d: {{ number_format($behaviorRadar['projection_60d'] * 100, 0) }}%</span>
    @endif
</div>
@endif
