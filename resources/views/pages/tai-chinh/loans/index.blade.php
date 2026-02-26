@extends('layouts.tai-chinh')

@section('taiChinhContent')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <nav class="flex items-center gap-2 text-theme-sm">
            <a href="{{ route('tai-chinh', ['tab' => 'no-khoan-vay']) }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">Tài chính</a>
            <span class="text-gray-400 dark:text-gray-500">/</span>
            <span class="font-medium text-gray-800 dark:text-white">Hợp đồng vay (liên kết)</span>
        </nav>
        <a href="{{ route('tai-chinh.loans.create') }}" class="inline-flex items-center gap-2 rounded-lg border border-brand-500 bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 focus:outline-none focus:ring-3 focus:ring-brand-500/10 dark:focus:ring-brand-800">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tạo hợp đồng liên kết
        </a>
    </div>
    @if(session('success'))
        <div class="mb-4 rounded-lg border border-success-200 bg-success-50 p-3 text-theme-sm text-success-700 dark:border-success-800 dark:bg-success-500/10 dark:text-success-400">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-lg border border-error-200 bg-error-50 p-3 text-theme-sm text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">{{ session('error') }}</div>
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-dark dark:text-white">
            <p class="text-theme-sm font-medium text-gray-500 dark:text-gray-400">Cho vay (sẽ thu)</p>
            <p class="mt-1 text-xl font-semibold text-success-600 dark:text-success-400">{{ number_format($summary['as_lender'] ?? 0) }} ₫</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-dark">
            <p class="text-theme-sm font-medium text-gray-500 dark:text-gray-400">Nợ (phải trả)</p>
            <p class="mt-1 text-xl font-semibold text-error-600 dark:text-error-400">{{ number_format($summary['as_borrower'] ?? 0) }} ₫</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-dark">
            <p class="text-theme-sm font-medium text-gray-500 dark:text-gray-400">Ròng đòn bẩy</p>
            <p class="mt-1 text-xl font-semibold {{ ($summary['net_leverage'] ?? 0) >= 0 ? 'text-success-600 dark:text-success-400' : 'text-error-600 dark:text-error-400' }}">{{ number_format($summary['net_leverage'] ?? 0) }} ₫</p>
        </div>
    </div>

    @if($contracts->isEmpty())
        <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-10 text-center shadow-theme-xs dark:border-gray-800 dark:bg-gray-900/50">
            <p class="text-theme-sm text-gray-600 dark:text-gray-400">Chưa có hợp đồng vay liên kết nào.</p>
            <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-500">Hợp đồng liên kết: cả bên cho vay và bên vay đều thấy cùng dữ liệu, không lệch.</p>
            <a href="{{ route('tai-chinh.loans.create') }}" class="mt-4 inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">Tạo hợp đồng</a>
        </div>
    @else
        <div x-data="{ showConfirmClose: false, formIdToSubmitClose: null }" @confirm-close-open.window="showConfirmClose = true; formIdToSubmitClose = $event.detail.formId" @confirm-close.window="if (formIdToSubmitClose) { const f = document.getElementById(formIdToSubmitClose); if (f) f.submit(); } formIdToSubmitClose = null; showConfirmClose = false">
        <div class="overflow-hidden rounded-xl border border-gray-200 shadow-theme-sm dark:border-gray-800">
            <div class="overflow-x-auto">
                <table class="min-w-full text-theme-sm">
                    <thead class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-700 dark:text-white">Tên</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700 dark:text-white">Vai trò</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-700 dark:text-white">Dư nợ gốc</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-700 dark:text-white">Lãi chưa thu/trả</th>
                            <th class="px-4 py-3 text-center font-medium text-gray-700 dark:text-white">Trạng thái</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-700 dark:text-white">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($contracts as $c)
                            @php
                                $ledger = app(\App\Services\LoanLedgerService::class);
                                $outstanding = $ledger->getOutstandingPrincipal($c);
                                $unpaid = $ledger->getUnpaidInterest($c);
                                $isLender = (int) $c->lender_user_id === (int) auth()->id();
                            @endphp
                            <tr class="bg-white dark:bg-gray-dark">
                                <td class="px-4 py-3">
                                    <a href="{{ route('tai-chinh.loans.show', $c->id) }}" class="font-medium text-gray-900 hover:text-brand-600 dark:text-white dark:hover:text-brand-400">{{ $c->name }}</a>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $c->borrowerDisplayName() }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($isLender)
                                        <span class="rounded-full bg-success-500/15 px-2 py-1 text-theme-xs font-medium text-success-700 dark:text-success-400">Cho vay</span>
                                    @else
                                        <span class="rounded-full bg-error-500/15 px-2 py-1 text-theme-xs font-medium text-error-700 dark:text-error-400">Đi vay</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-medium">{{ number_format($outstanding) }} ₫</td>
                                <td class="px-4 py-3 text-right text-amber-600 dark:text-amber-400">{{ number_format($unpaid) }} ₫</td>
                                <td class="px-4 py-3 text-center">
                                    @if($c->status === 'pending')
                                        <span class="rounded-full bg-warning-500/15 px-2 py-1 text-theme-xs font-medium text-warning-700 dark:text-warning-400">Chờ xác nhận</span>
                                    @elseif($c->status === 'active')
                                        <span class="rounded-full bg-success-500/15 px-2 py-1 text-theme-xs font-medium text-success-700 dark:text-success-400">Đang hoạt động</span>
                                    @else
                                        <span class="rounded-full bg-gray-200 px-2 py-1 text-theme-xs font-medium text-gray-600 dark:bg-gray-600 dark:text-gray-300">Đã đóng</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap justify-end gap-1">
                                        @if($c->status === 'pending' && (int) $c->borrower_user_id === (int) auth()->id())
                                            <form method="POST" action="{{ route('tai-chinh.loans.accept', $c->id) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="rounded-lg border border-brand-500 bg-brand-500 px-2.5 py-1.5 text-theme-xs font-medium text-white shadow-theme-xs hover:bg-brand-600">Chấp nhận</button>
                                            </form>
                                        @endif
                                        @if($c->status === 'active')
                                            <a href="{{ route('tai-chinh.loans.payment', $c->id) }}" class="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-theme-xs font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.05]">{{ $isLender ? 'Thu nợ' : 'Thanh toán' }}</a>
                                            <form id="form-close-loan-{{ $c->id }}" method="POST" action="{{ route('tai-chinh.loans.close', $c->id) }}" class="inline">
                                                @csrf
                                                <button type="button" @click="$dispatch('confirm-close-open', { formId: 'form-close-loan-{{ $c->id }}' })" class="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-theme-xs font-medium text-gray-600 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.05]">Đóng</button>
                                            </form>
                                        @endif
                                        <a href="{{ route('tai-chinh.loans.show', $c->id) }}" class="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-theme-xs font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.05]">Chi tiết</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <x-ui.confirm-delete openVar="showConfirmClose" title="Xác nhận đóng" defaultMessage="Đóng hợp đồng này?" confirmText="Đóng" confirmEvent="confirm-close" />
        </div>
    @endif
@endsection
