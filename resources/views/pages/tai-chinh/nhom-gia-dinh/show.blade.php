@php
    $householdAnalytics = $householdMonthlyAnalytics ?? ['monthly' => [], 'summary' => ['total_thu' => 0, 'total_chi' => 0, 'net_cashflow' => 0, 'pct_change_net' => null], 'has_actual_data' => false];
    $monthlyList = $householdAnalytics['monthly'] ?? [];
    $summary = $householdAnalytics['summary'] ?? null;
    $hasMonthly = !empty($monthlyList);
@endphp
@extends('layouts.tai-chinh')

@section('taiChinhContent')
<div class="space-y-4" id="household-show-content">
    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">{{ session('error') }}</div>
    @endif
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $household->name }}</h2>
            <span class="text-sm text-gray-500 dark:text-gray-400">Tài khoản: {{ $household->owner->name ?? '—' }}</span>
            <span class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm font-medium text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                Số dư: {{ number_format($totalBalance ?? 0, 0, ',', '.') }} ₫
            </span>
        </div>
        @if($canEdit)
            <form method="POST" action="{{ route('tai-chinh.nhom-gia-dinh.members.store', $household->id) }}" class="flex flex-wrap items-center gap-2">
                @csrf
                <input type="email" name="email" value="{{ old('email') }}" placeholder="Email thành viên" required class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">Thêm thành viên</button>
                @error('email')<span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span>@enderror
            </form>
        @endif
    </div>

    {{-- Thẻ thu chi + sparkline (12 tháng) --}}
    @if($summary || $hasMonthly)
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        @if($summary)
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                <p class="mb-1 text-theme-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Tổng thu (12 tháng)</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ number_format($summary['total_thu']) }} ₫</p>
                @if($hasMonthly)
                    <div class="mt-2 h-8 w-full" data-sparkline="{{ json_encode(array_map(fn($m) => (float)$m['thu'], $monthlyList)) }}" data-sparkline-color="#22c55e"></div>
                @endif
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                <p class="mb-1 text-theme-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Tổng chi (12 tháng)</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ number_format($summary['total_chi']) }} ₫</p>
                @if($hasMonthly)
                    <div class="mt-2 h-8 w-full" data-sparkline="{{ json_encode(array_map(fn($m) => (float)$m['chi'], $monthlyList)) }}" data-sparkline-color="#ef4444"></div>
                @endif
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                <p class="mb-1 text-theme-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Dòng tiền ròng</p>
                @php $net = $summary['net_cashflow'] ?? 0; @endphp
                <p class="text-lg font-semibold {{ $net >= 0 ? 'text-success-700 dark:text-success-400' : 'text-error-700 dark:text-error-400' }}">{{ $net >= 0 ? '+' : '' }}{{ number_format($net) }} ₫</p>
                @if(isset($summary['pct_change_net']) && $summary['pct_change_net'] !== null)
                    <p class="mt-0.5 text-theme-xs {{ $summary['pct_change_net'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-error-600 dark:text-error-400' }}">{{ $summary['pct_change_net'] >= 0 ? '+' : '' }}{{ number_format($summary['pct_change_net'], 1) }}% so với kỳ trước</p>
                @endif
                @if($hasMonthly)
                    <div class="mt-2 h-8 w-full" data-sparkline="{{ json_encode(array_map(fn($m) => (float)$m['surplus'], $monthlyList)) }}" data-sparkline-color="{{ $net >= 0 ? '#22c55e' : '#ef4444' }}"></div>
                @endif
            </div>
        @endif
    </div>
    @endif

    <form method="GET" action="{{ route('tai-chinh.nhom-gia-dinh.show', $household->id) }}" id="form-household-giao-dich-filter" class="mb-4 flex flex-wrap items-center gap-3">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Tìm mô tả, Số TK..."
            class="h-10 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
        @if(count($linkedAccountNumbers ?? []) > 1)
            <select name="stk" class="h-10 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <option value="">Tất cả Số TK</option>
                @foreach($linkedAccountNumbers ?? [] as $num)
                    <option value="{{ $num }}" {{ request('stk') === $num ? 'selected' : '' }}>{{ $num }}</option>
                @endforeach
            </select>
        @endif
        <select name="loai" class="h-10 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            <option value="">Tất cả</option>
            <option value="IN" {{ request('loai') === 'IN' ? 'selected' : '' }}>Vào</option>
            <option value="OUT" {{ request('loai') === 'OUT' ? 'selected' : '' }}>Ra</option>
        </select>
        <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">Lọc</button>
    </form>

    <div id="giao-dich-table-container">
        @include('pages.tai-chinh.partials.giao-dich-table')
    </div>
</div>

@if($hasMonthly && !empty($monthlyList))
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.ApexCharts === 'undefined') return;
    document.querySelectorAll('#household-show-content [data-sparkline]').forEach(function(div) {
        var raw = div.getAttribute('data-sparkline');
        var color = div.getAttribute('data-sparkline-color') || '#465fff';
        if (!raw) return;
        try {
            var arr = JSON.parse(raw);
            if (!arr || arr.length === 0) return;
            div.innerHTML = '';
            var chart = new window.ApexCharts(div, {
                series: [{ name: '', data: arr }],
                chart: { type: 'area', height: 32, sparkline: { enabled: true }, animations: { enabled: false } },
                stroke: { curve: 'smooth', width: 1.5 },
                fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0 } },
                colors: [color],
                tooltip: { fixed: { enabled: false }, y: { formatter: function(v) { return new Intl.NumberFormat('vi-VN').format(v) + ' ₫'; } } }
            });
            chart.render();
        } catch (e) {}
    });
});
</script>
@endif
@endsection

@section('taiChinhRightColumn')
@endsection
