@extends('layouts.tai-chinh')

@section('taiChinhContent')
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3" x-data="{ showConfirmDelete: false, showConfirmClose: false }" @confirm-delete.window="const f = document.getElementById('form-delete-liability'); if (f) f.submit();" @confirm-close.window="const f = document.getElementById('form-close-liability'); if (f) f.submit();">
        <nav class="flex items-center gap-1.5 text-theme-sm">
            <a href="{{ route('tai-chinh', ['tab' => 'no-khoan-vay']) }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">Tài chính</a>
            <span class="text-gray-400 dark:text-gray-500">→</span>
            <span class="font-medium text-gray-800 dark:text-white">{{ $liability->name }}</span>
        </nav>
        <div class="flex flex-wrap gap-2">
            @if($liability->status === \App\Models\UserLiability::STATUS_ACTIVE)
                <a href="{{ route('tai-chinh.liability.thanh-toan', $liability->id) }}" class="rounded-lg bg-brand-500 px-3.5 py-2 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">{{ $liability->isReceivable() ? 'Thu nợ' : 'Thanh toán' }}</a>
                <a href="{{ route('tai-chinh.liability.ghi-lai', $liability->id) }}" class="rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-dark dark:text-gray-300 dark:hover:bg-gray-800">Ghi lãi tay</a>
                <form id="form-close-liability" method="POST" action="{{ route('tai-chinh.liability.close', $liability->id) }}" class="inline">
                    @csrf
                    <button type="button" @click="showConfirmClose = true" class="rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-theme-sm font-medium text-gray-600 shadow-theme-xs hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-dark dark:text-gray-400 dark:hover:bg-gray-800">Đóng</button>
                </form>
            @endif
            <form id="form-delete-liability" method="POST" action="{{ route('tai-chinh.liability.destroy', $liability->id) }}" class="inline">
                @csrf
                @method('DELETE')
                <button type="button" @click="showConfirmDelete = true" class="rounded-lg border border-error-200 bg-error-50 px-3.5 py-2 text-theme-sm font-medium text-error-600 shadow-theme-xs hover:bg-error-100 dark:border-error-800 dark:bg-error-900/20 dark:text-error-400 dark:hover:bg-error-900/30">Xóa</button>
            </form>
            <a href="{{ route('tai-chinh', ['tab' => 'no-khoan-vay']) }}" class="rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-dark dark:text-gray-300 dark:hover:bg-gray-800">← Quay lại</a>
        </div>
        <x-ui.confirm-delete openVar="showConfirmDelete" title="Xác nhận xóa" defaultMessage="Xóa khoản nợ / khoản vay này? Dữ liệu sẽ mất vĩnh viễn." />
        <x-ui.confirm-delete openVar="showConfirmClose" title="Xác nhận đóng" defaultMessage="Đóng khoản nợ này?" confirmText="Đóng" confirmEvent="confirm-close" />
    </div>

    @php
        $interestLabel = match($liability->interest_unit) {
            'yearly' => 'năm',
            'monthly' => 'tháng',
            'daily' => 'ngày',
            default => 'năm',
        };
        $calcLabel = $liability->interest_calculation === 'compound' ? 'lãi kép' : 'lãi đơn';
    @endphp
    <div class="space-y-5">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-dark dark:text-white">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-theme-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">{{ $liability->direction_label }}</p>
                    <p class="mt-1 text-theme-sm font-medium text-gray-700 dark:text-white">{{ number_format((float)$liability->interest_rate, 1) }}% / {{ $interestLabel }} · {{ $calcLabel }}</p>
                    <p class="mt-0.5 text-theme-xs font-medium text-gray-600 dark:text-gray-400">{{ $liability->start_date->format('d/m/Y') }}@if($liability->due_date) — {{ $liability->due_date->format('d/m/Y') }}@endif</p>
                </div>
                <div class="flex shrink-0 gap-6 sm:gap-8">
                    <div>
                        <p class="text-theme-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">Dư nợ gốc</p>
                        <p class="mt-0.5 text-lg font-bold tabular-nums text-gray-900 dark:text-white">{{ number_format($outstanding) }} ₫</p>
                    </div>
                    <div>
                        <p class="text-theme-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">Lãi chưa thu/trả</p>
                        <p class="mt-0.5 text-lg font-bold tabular-nums text-warning-600 dark:text-warning-400">{{ number_format($unpaidInterest) }} ₫</p>
                    </div>
                </div>
            </div>
            <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-theme-sm font-semibold text-gray-700 dark:text-white">Tiến độ trả nợ gốc</span>
                    <span class="text-theme-sm font-bold tabular-nums text-gray-900 dark:text-white">{{ $progressPercent }}%</span>
                </div>
                <div class="h-2.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                    <div class="h-full rounded-full bg-brand-500 transition-all duration-500 dark:bg-brand-500" style="width: {{ $progressPercent }}%;"></div>
                </div>
                <p class="mt-1 text-theme-xs font-medium text-gray-600 dark:text-gray-400">Đã trả {{ number_format($repaid) }} ₫ / {{ number_format($principalStart) }} ₫ gốc ban đầu</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-dark dark:text-white">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-2.5 dark:border-gray-800">
                <h3 class="text-theme-sm font-bold text-gray-800 dark:text-white">Lịch sử giao dịch</h3>
                <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Gốc ban đầu {{ number_format($principalStart) }} ₫</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800">
                            <th class="px-4 py-2.5 text-left text-theme-xs font-bold uppercase tracking-wider text-gray-600 dark:text-white">Ngày</th>
                            <th class="px-4 py-2.5 text-left text-theme-xs font-bold uppercase tracking-wider text-gray-600 dark:text-white">Loại</th>
                            <th class="px-4 py-2.5 text-right text-theme-xs font-bold uppercase tracking-wider text-gray-600 dark:text-white">Δ Gốc</th>
                            <th class="px-4 py-2.5 text-right text-theme-xs font-bold uppercase tracking-wider text-gray-600 dark:text-white">Δ Lãi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($entries as $e)
                            <tr class="hover:bg-gray-50/50 dark:bg-gray-dark dark:hover:bg-gray-800 dark:text-white">
                                <td class="px-4 py-2.5 tabular-nums font-medium text-theme-sm text-gray-700 dark:text-gray-300">{{ $e->date?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-4 py-2.5">
                                    @if($e->type === 'accrual')
                                        <span class="inline-flex rounded-md bg-warning-50 px-2 py-0.5 text-theme-xs font-medium text-warning-700 dark:bg-warning-500/15 dark:text-warning-400">Lãi</span>
                                    @else
                                        <span class="inline-flex rounded-md bg-success-50 px-2 py-0.5 text-theme-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-400">Thanh toán</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums {{ $e->principal_delta != 0 ? ($e->principal_delta > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400') : 'text-gray-400' }}">
                                    @if($e->principal_delta != 0){{ $e->principal_delta > 0 ? '+' : '' }}{{ number_format($e->principal_delta) }} @else — @endif
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums {{ $e->interest_delta != 0 ? ($e->interest_delta > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400') : 'text-gray-400' }}">
                                    @if($e->interest_delta != 0){{ $e->interest_delta > 0 ? '+' : '' }}{{ number_format($e->interest_delta) }} @else — @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-10 text-center">
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Chưa có giao dịch</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
