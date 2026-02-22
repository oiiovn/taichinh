@extends('layouts.tai-chinh')

@section('taiChinhContent')
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <nav class="flex items-center gap-2 text-theme-sm">
            <a href="{{ route('tai-chinh', ['tab' => 'no-khoan-vay']) }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">Tài chính</a>
            <span class="text-gray-400 dark:text-gray-500">/</span>
            <a href="{{ route('tai-chinh.loans.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">Hợp đồng liên kết</a>
            <span class="text-gray-400 dark:text-gray-500">/</span>
            <a href="{{ route('tai-chinh.loans.show', $contract->id) }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">{{ $contract->name }}</a>
            <span class="text-gray-400 dark:text-gray-500">/</span>
            <span class="font-medium text-gray-800 dark:text-white">{{ $contract->borrower_user_id === auth()->id() ? 'Thanh toán' : 'Thu nợ' }}</span>
        </nav>
    </div>
    @if(session('error'))
        <div class="mb-4 rounded-lg border border-error-200 bg-error-50 p-3 text-theme-sm text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-lg border border-warning-200 bg-warning-50 p-3 text-theme-sm text-warning-800 dark:border-warning-800 dark:bg-warning-500/10 dark:text-warning-300">
            <ul class="list-inside list-disc">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    @php
        $isReducing = $contract->interest_calculation === \App\Models\LoanContract::INTEREST_CALCULATION_REDUCING;
        $suggestedPeriodic = round((float) $unpaidInterest + (float) $outstanding / 12, 0);
    @endphp
    <div class="mx-auto max-w-xl rounded-xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-dark">
        <h2 class="mb-2 text-theme-xl font-semibold text-gray-900 dark:text-white">{{ $contract->borrower_user_id === auth()->id() ? 'Thanh toán' : 'Thu nợ' }}</h2>
        <p class="mb-4 text-theme-sm text-gray-600 dark:text-gray-400">Hợp đồng: <strong>{{ $contract->name }}</strong>. Dư nợ gốc: <strong>{{ number_format($outstanding) }} ₫</strong>. Lãi chưa thu/trả: <strong>{{ number_format($unpaidInterest) }} ₫</strong>.</p>
        <form method="POST" action="{{ route('tai-chinh.loans.payment.store', $contract->id) }}" class="space-y-4">
            @csrf
            <div>
                <label class="mb-2 block text-theme-sm font-medium text-gray-700 dark:text-gray-300">Chọn loại</label>
                <div class="space-y-2">
                    <label class="flex cursor-pointer items-center gap-2">
                        <input type="radio" name="pay_type" value="principal" {{ old('pay_type', 'principal') === 'principal' ? 'checked' : '' }}
                            class="h-4 w-4 border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-600">
                        <span class="text-theme-sm">Chỉ thu gốc</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-2">
                        <input type="radio" name="pay_type" value="interest" {{ old('pay_type') === 'interest' ? 'checked' : '' }}
                            class="h-4 w-4 border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-600">
                        <span class="text-theme-sm">Chỉ thu lãi</span>
                    </label>
                    @if($isReducing)
                        <label class="flex cursor-pointer items-center gap-2">
                            <input type="radio" name="pay_type" value="periodic" {{ old('pay_type') === 'periodic' ? 'checked' : '' }}
                                class="h-4 w-4 border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-600">
                            <span class="text-theme-sm">Khoản thanh toán định kỳ</span>
                            <span class="text-theme-xs text-gray-500">(gợi ý ~{{ number_format($suggestedPeriodic) }} ₫)</span>
                        </label>
                    @endif
                </div>
            </div>
            <div>
                <label for="amount" class="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-300">Số tiền (₫)</label>
                <input type="text" id="amount" name="amount" value="{{ old('amount') }}" required inputmode="numeric" data-format-vnd
                    class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:focus:border-brand-800"
                    @if($isReducing) placeholder="{{ number_format($suggestedPeriodic) }}" @endif>
            </div>
            <div>
                <label for="paid_at" class="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-300">Ngày</label>
                <input type="date" id="paid_at" name="paid_at" value="{{ old('paid_at', date('Y-m-d')) }}" required
                    class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:focus:border-brand-800">
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-200 pt-4 dark:border-gray-700">
                <a href="{{ route('tai-chinh.loans.show', $contract->id) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.05]">Hủy</a>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 focus:outline-none focus:ring-3 focus:ring-brand-500/10 dark:focus:ring-brand-800">Ghi nhận</button>
            </div>
        </form>
    </div>
@endsection
