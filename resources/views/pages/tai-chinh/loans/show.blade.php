@extends('layouts.tai-chinh')

@section('taiChinhContent')
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3" x-data="{ showConfirmDelete: false, showConfirmClose: false }" @confirm-delete.window="const f = document.getElementById('form-delete-loan'); if (f) f.submit();" @confirm-close.window="const f = document.getElementById('form-close-loan'); if (f) f.submit();">
        <nav class="flex items-center gap-1.5 text-theme-sm">
            <a href="{{ route('tai-chinh', ['tab' => 'no-khoan-vay']) }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">Tài chính</a>
            <span class="text-gray-400 dark:text-gray-500">→</span>
            <a href="{{ route('tai-chinh.loans.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">Hợp đồng</a>
            <span class="text-gray-400 dark:text-gray-500">→</span>
            <span class="font-medium text-gray-800 dark:text-white">{{ $contract->name }}</span>
        </nav>
        <div class="flex flex-wrap gap-2">
            @if($contract->status === 'pending' && $contract->borrower_user_id === auth()->id())
                <form method="POST" action="{{ route('tai-chinh.loans.accept', $contract->id) }}" class="inline">
                    @csrf
                    <button type="submit" class="rounded-lg border border-brand-500 bg-brand-500 px-3.5 py-2 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">Chấp nhận</button>
                </form>
            @endif
            @if($contract->status === 'active')
                <a href="{{ route('tai-chinh.loans.payment', $contract->id) }}" class="rounded-lg bg-brand-500 px-3.5 py-2 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">{{ $contract->borrower_user_id === auth()->id() ? 'Thanh toán' : 'Thu nợ' }}</a>
                <form id="form-close-loan" method="POST" action="{{ route('tai-chinh.loans.close', $contract->id) }}" class="inline">
                    @csrf
                    <button type="button" @click="showConfirmClose = true" class="rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-theme-sm font-medium text-gray-600 shadow-theme-xs hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-dark dark:text-gray-400 dark:hover:bg-gray-800">Đóng</button>
                </form>
            @endif
            <form id="form-delete-loan" method="POST" action="{{ route('tai-chinh.loans.destroy', $contract->id) }}" class="inline">
                @csrf
                @method('DELETE')
                <button type="button" @click="showConfirmDelete = true" class="rounded-lg border border-error-200 bg-error-50 px-3.5 py-2 text-theme-sm font-medium text-error-600 shadow-theme-xs hover:bg-error-100 dark:border-error-800 dark:bg-error-900/20 dark:text-error-400 dark:hover:bg-error-900/30">Xóa</button>
            </form>
            <a href="{{ route('tai-chinh.loans.index') }}" class="rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-dark dark:text-gray-300 dark:hover:bg-gray-800">← Quay lại</a>
        </div>
        <x-ui.confirm-delete openVar="showConfirmDelete" title="Xác nhận xóa" defaultMessage="Xóa hợp đồng này? Dữ liệu sẽ mất vĩnh viễn." />
        <x-ui.confirm-delete openVar="showConfirmClose" title="Xác nhận đóng" defaultMessage="Đóng hợp đồng này?" confirmText="Đóng" confirmEvent="confirm-close" />
    </div>

    @php
        $principalStart = (float) $contract->principal_at_start;
        $repaid = $principalStart > 0 ? max(0, $principalStart - $outstanding) : 0;
        $progressPercent = $principalStart > 0 ? min(100, round($repaid / $principalStart * 100, 1)) : 0;
    @endphp
    <div class="space-y-5">
        {{-- Thông tin + Số liệu + Tiến độ (TailAdmin) --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-dark dark:text-white">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-theme-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">{{ $contract->lender?->name ?? '—' }} → {{ $contract->borrowerDisplayName() }}</p>
                    <p class="mt-1 text-theme-sm font-medium text-gray-700 dark:text-white">{{ number_format((float)$contract->interest_rate, 1) }}% / {{ $contract->interest_unit === 'yearly' ? 'năm' : ($contract->interest_unit === 'monthly' ? 'tháng' : 'ngày') }} · {{ $contract->interestCalculationLabel() }}</p>
                    <p class="mt-0.5 text-theme-xs font-medium text-gray-600 dark:text-gray-400">{{ $contract->start_date->format('d/m/Y') }}@if($contract->due_date) — {{ $contract->due_date->format('d/m/Y') }}@endif</p>
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

        {{-- Chờ thanh toán (TailAdmin) --}}
        @if($contract->isLinked() && $pendingPayments->isNotEmpty())
            <div class="rounded-xl border border-warning-200 bg-warning-50/50 shadow-theme-sm dark:border-warning-800/50 dark:bg-warning-500/5">
                <div class="border-b border-warning-200 px-4 py-2.5 dark:border-warning-800/50">
                    <h3 class="text-theme-sm font-semibold text-warning-800 dark:text-warning-400">Chờ thanh toán</h3>
                </div>
                <div class="divide-y divide-warning-200/80 dark:divide-warning-800/50">
                    @foreach($pendingPayments as $pp)
                        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <div>
                                <p class="text-sm font-medium text-gray-800 dark:text-white">{{ $pp->due_date->format('d/m/Y') }} · {{ number_format((float)$pp->expected_principal + (float)$pp->expected_interest) }} ₫</p>
                                <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-400">Nội dung CK: <code class="rounded bg-amber-100 px-1 py-0.5 dark:bg-amber-900/40">{{ $pp->match_content }}</code></p>
                                @if($pp->status === 'pending_counterparty_confirm')
                                    <p class="mt-1 text-xs text-amber-700 dark:text-amber-500">Chờ {{ $pp->recordedByUser?->name ?? 'đối phương' }} xác nhận</p>
                                @endif
                            </div>
                            <div class="flex gap-2">
                                @if($pp->status === 'awaiting_payment')
                                    <form method="POST" action="{{ route('tai-chinh.loans.pending.record', $pp->id) }}" class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        <select name="payment_method" class="rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-theme-xs shadow-theme-xs dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                            <option value="bank">Chuyển khoản</option>
                                            <option value="cash">Tiền mặt</option>
                                        </select>
                                        <input type="text" name="bank_transaction_ref" placeholder="Mã GD (tùy chọn)" maxlength="100"
                                            class="rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-theme-xs shadow-theme-xs dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                        <button type="submit" class="rounded-lg bg-success-600 px-3 py-1.5 text-theme-xs font-medium text-white shadow-theme-xs hover:bg-success-700">Đã thanh toán</button>
                                    </form>
                                @elseif($pp->needsCounterpartyConfirm(auth()->id()))
                                    <form method="POST" action="{{ route('tai-chinh.loans.pending.confirm', $pp->id) }}">
                                        @csrf
                                        <button type="submit" class="rounded-lg bg-brand-500 px-3 py-1.5 text-theme-xs font-medium text-white shadow-theme-xs hover:bg-brand-600">Xác nhận đã thanh toán</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Chờ xác nhận thanh toán (ghi từ form Thu nợ / Thanh toán) --}}
        @if($contract->isLinked() && $pendingLedgerPayments->isNotEmpty())
            <style>
                @keyframes pending-blink {
                    0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(234, 179, 8, 0.35); }
                    50% { opacity: 0.92; box-shadow: 0 0 0 6px rgba(234, 179, 8, 0); }
                }
                .pending-card-blink {
                    animation: pending-blink 2s ease-in-out infinite;
                }
                @keyframes icon-wait {
                    0%, 100% { transform: rotate(0deg); opacity: 1; }
                    25% { transform: rotate(-8deg); opacity: 0.9; }
                    75% { transform: rotate(8deg); opacity: 0.9; }
                }
                .pending-icon-wait {
                    animation: icon-wait 1.5s ease-in-out infinite;
                }
            </style>
            <div class="flex flex-wrap gap-2">
                @foreach($pendingLedgerPayments as $e)
                    @php
                        $canConfirm = (auth()->id() === (int)$contract->lender_user_id && $e->source === \App\Models\LoanLedgerEntry::SOURCE_BORROWER)
                            || (auth()->id() === (int)$contract->borrower_user_id && $e->source === \App\Models\LoanLedgerEntry::SOURCE_LENDER);
                    @endphp
                    <div class="pending-card-blink inline-flex flex-wrap items-center gap-2 rounded-lg border-2 border-yellow-400 bg-yellow-200 px-3 py-2 dark:border-yellow-500 dark:bg-yellow-500/40">
                        <span class="pending-icon-wait inline-flex shrink-0 text-yellow-700 dark:text-yellow-200" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </span>
                        <span class="text-sm font-semibold text-yellow-900 dark:text-yellow-100">Chờ xác nhận thanh toán</span>
                        <span class="text-sm text-yellow-800 dark:text-yellow-200">{{ $e->effective_date?->format('d/m/Y') ?? $e->created_at->format('d/m/Y') }} · Gốc {{ number_format(abs((float)$e->principal_delta)) }} ₫@if((float)$e->interest_delta != 0) · Lãi {{ number_format(abs((float)$e->interest_delta)) }} ₫@endif</span>
                        <span class="text-xs text-yellow-700 dark:text-yellow-300">{{ $e->createdByUser?->name ?? '—' }} đã ghi nhận</span>
                        @if($canConfirm)
                            <span class="ml-1 inline-flex gap-1">
                                <form method="POST" action="{{ route('tai-chinh.loans.ledger.confirm', [$contract->id, $e->id]) }}" class="inline">@csrf<button type="submit" class="rounded-lg border border-lime-500 bg-lime-500 px-3 py-1.5 text-xs font-semibold text-white shadow-theme-xs hover:bg-lime-600 hover:border-lime-600 dark:border-emerald-500 dark:bg-emerald-600 dark:text-white dark:hover:bg-emerald-500">Xác nhận</button></form>
                                <form method="POST" action="{{ route('tai-chinh.loans.ledger.reject', [$contract->id, $e->id]) }}" class="inline">@csrf<button type="submit" class="rounded-lg border border-gray-300 bg-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-900 shadow-theme-xs hover:bg-gray-300 hover:border-gray-400 dark:border-error-500 dark:bg-error-600 dark:text-white dark:hover:bg-error-500">Từ chối</button></form>
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Sổ cái compact (TailAdmin) --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-dark dark:text-white">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-2.5 dark:border-gray-800">
                <h3 class="text-theme-sm font-bold text-gray-800 dark:text-white">Lịch sử giao dịch</h3>
                <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Gốc ban đầu {{ number_format($contract->principal_at_start) }} ₫</span>
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
                        @forelse($contract->ledgerEntries->where('status', \App\Models\LoanLedgerEntry::STATUS_CONFIRMED)->sortByDesc('created_at') as $e)
                            <tr class="hover:bg-gray-50/50 dark:bg-gray-dark dark:hover:bg-gray-800 dark:text-white">
                                <td class="px-4 py-2.5 tabular-nums font-medium text-theme-sm text-gray-700 dark:text-gray-300">{{ ($e->effective_date ?? $e->created_at)->format('d/m/Y') }} {{ $e->created_at->timezone('Asia/Ho_Chi_Minh')->format('H:i') }}</td>
                                <td class="px-4 py-2.5">
                                    @if($e->type === 'accrual')
                                        <span class="inline-flex rounded-md bg-warning-50 px-2 py-0.5 text-theme-xs font-medium text-warning-700 dark:bg-warning-500/15 dark:text-warning-400">Lãi</span>
                                    @elseif($e->type === 'payment')
                                        <span class="inline-flex rounded-md bg-success-50 px-2 py-0.5 text-theme-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-400">Thanh toán</span>
                                    @else
                                        <span class="inline-flex rounded-md bg-gray-100 px-2 py-0.5 text-theme-xs font-medium text-gray-600 dark:bg-gray-600/30 dark:text-gray-400">Điều chỉnh</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums {{ $e->principal_delta != 0 ? ($e->principal_delta > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400') : 'text-gray-400' }}">
                                    @if($e->principal_delta != 0){{ $e->principal_delta > 0 ? '+' : '' }}{{ number_format((float)$e->principal_delta) }} @else — @endif
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums {{ $e->interest_delta != 0 ? ($e->interest_delta > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400') : 'text-gray-400' }}">
                                    @if($e->interest_delta != 0){{ $e->interest_delta > 0 ? '+' : '' }}{{ number_format((float)$e->interest_delta) }} @else — @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-10 text-center">
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Chưa có giao dịch</p>
                                    <p class="mt-1 text-xs font-medium text-gray-500 dark:text-gray-400">Lãi tự động chạy hàng ngày lúc 3h15</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
