@php
    $position = $position ?? ['net_leverage' => 0, 'debt_exposure' => 0, 'receivable_exposure' => 0, 'risk_level' => 'low', 'risk_label' => 'Thấp', 'risk_color' => 'green'];
@endphp
@php
    $openModal = session('open_modal', '');
    $noKhoanVayModalData = [
        'modalLiability' => $openModal === 'liability',
        'modalLoan' => $openModal === 'loan',
    ];
@endphp
<div class="flex flex-wrap items-center justify-between gap-4" x-data='@json($noKhoanVayModalData)' @no-khoan-vay-modal-close.window="modalLiability = false; modalLoan = false" @modal-form-submitted.window="modalLiability = false; modalLoan = false">
    <h2 class="text-theme-xl font-semibold text-gray-900 dark:text-white">Nợ & Khoản vay</h2>
    <div class="flex flex-wrap items-center gap-2">
        <button type="button" @click="modalLiability = true" class="inline-flex items-center gap-2 rounded-lg border border-brand-500 bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 focus:outline-none focus:ring-3 focus:ring-brand-500/10 dark:focus:ring-brand-800">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Thêm khoản nợ / vay
        </button>
        <button type="button" @click="modalLoan = true" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-dark dark:text-gray-300 dark:hover:bg-gray-800">Tạo hợp đồng liên kết</button>
    </div>
    <style>[x-cloak]{display:none !important}</style>
    <x-ui.modal-form openVar="modalLiability" title="Thêm khoản nợ / vay">
        @if($errors->any() && $openModal === 'liability')
            <x-slot:validationErrors>
                <ul class="list-inside list-disc">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </x-slot:validationErrors>
        @endif
        @include('pages.tai-chinh.liability.partials.form', ['inModal' => true])
    </x-ui.modal-form>
    <x-ui.modal-form openVar="modalLoan" title="Tạo hợp đồng liên kết">
        @if($errors->any() && $openModal === 'loan')
            <x-slot:validationErrors>
                <ul class="list-inside list-disc">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </x-slot:validationErrors>
        @endif
        @include('pages.tai-chinh.loans.partials.form', ['inModal' => true])
    </x-ui.modal-form>
</div>

{{-- TẦNG 1 — FINANCIAL POSITION --}}
@php
    $net = (float) ($position['net_leverage'] ?? 0);
    $netClass = $net > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($net < 0 ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400');
    $riskBg = match($position['risk_color'] ?? 'green') {
        'red' => 'bg-error-500',
        'yellow' => 'bg-warning-500',
        default => 'bg-success-500',
    };
@endphp
<div class="mt-6 flex flex-wrap gap-4">
    <div class="min-w-0 flex-1 rounded-xl border border-gray-200 bg-white p-4 shadow-theme-sm dark:border-gray-800 dark:bg-gray-dark dark:text-white" style="min-width: 140px;">
        <p class="text-theme-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Ròng đòn bẩy</p>
        <div class="mt-1 flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-700">
                <svg class="h-5 w-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                </svg>
            </span>
            <p class="text-2xl font-bold tabular-nums {{ $netClass }}">{{ $net >= 0 ? '+' : '' }}{{ number_format($net) }} ₫</p>
        </div>
    </div>
    <div class="min-w-0 flex-1 rounded-xl border border-gray-200 bg-white p-4 shadow-theme-sm dark:border-gray-800 dark:bg-gray-dark dark:text-white" style="min-width: 140px;">
        <p class="text-theme-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Tổng nợ</p>
        <div class="mt-1 flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-error-100 dark:bg-error-900/30">
                <svg class="h-5 w-5 text-error-600 dark:text-error-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                </svg>
            </span>
            <p class="text-2xl font-bold tabular-nums text-error-600 dark:text-error-400">{{ number_format($position['debt_exposure'] ?? 0) }} ₫</p>
        </div>
    </div>
    <div class="min-w-0 flex-1 rounded-xl border border-gray-200 bg-white p-4 shadow-theme-sm dark:border-gray-800 dark:bg-gray-dark dark:text-white" style="min-width: 140px;">
        <p class="text-theme-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Tổng khoản cho vay</p>
        <div class="mt-1 flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-success-100 dark:bg-success-900/30">
                <svg class="h-5 w-5 text-success-600 dark:text-success-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10l7-7m0 0l7 7m-7-7v11"/>
                </svg>
            </span>
            <p class="text-2xl font-bold tabular-nums text-success-600 dark:text-success-400">{{ number_format($position['receivable_exposure'] ?? 0) }} ₫</p>
        </div>
    </div>
</div>
<div class="mt-3 flex items-center gap-3">
    <span class="text-theme-sm font-medium text-gray-600 dark:text-gray-300">Mức rủi ro:</span>
    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-theme-sm font-semibold
        {{ $position['risk_level'] === 'high' ? 'bg-error-100 text-error-800 dark:bg-error-900/30 dark:text-error-400' : '' }}
        {{ $position['risk_level'] === 'medium' ? 'bg-warning-100 text-warning-800 dark:bg-warning-900/30 dark:text-warning-400' : '' }}
        {{ $position['risk_level'] === 'low' ? 'bg-success-100 text-success-800 dark:bg-success-900/30 dark:text-success-400' : '' }}">
        <span class="h-2 w-2 rounded-full {{ $riskBg }}"></span>
        {{ $position['risk_label'] ?? 'Thấp' }}
    </span>
</div>
