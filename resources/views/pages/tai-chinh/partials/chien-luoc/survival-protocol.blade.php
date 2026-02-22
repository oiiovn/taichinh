@php
    $directive = $insightPayload['survival_directive'] ?? null;
@endphp
@if($directive)
<section class="rounded-xl border-2 border-error-200 bg-error-50/80 p-5 dark:border-error-800 dark:bg-error-900/30 dark:text-white" aria-label="Giao thức sinh tồn">
    @if(!empty($directive['subtitle']))
        <p class="mb-4 text-theme-sm text-error-700 dark:text-error-300">{{ $directive['subtitle'] }}</p>
    @endif
    @if(!empty($directive['action_7_days']))
        <p class="mb-1.5 text-theme-xs font-semibold uppercase tracking-wider text-error-600 dark:text-error-400">Hành động trong 7 ngày</p>
        <ul class="list-disc pl-5 space-y-1 text-theme-sm text-error-800 dark:text-error-200">
            @foreach($directive['action_7_days'] as $line)
                <li>{{ $line }}</li>
            @endforeach
        </ul>
    @endif
    @if(!empty($directive['goal_30_45_days']))
        <p class="mt-4 mb-1.5 text-theme-xs font-semibold uppercase tracking-wider text-error-600 dark:text-error-400">Mục tiêu 30–45 ngày</p>
        <p class="text-theme-sm text-error-800 dark:text-error-200">{{ $directive['goal_30_45_days'] }}</p>
    @endif
</section>
@endif
