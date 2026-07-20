@extends('layouts.food')

@section('foodContent')
@php
    $fmt = fn ($n) => \App\Helpers\BaoCaoHelper::formatGiaVonNguyen($n);
    $inputClass = 'w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-100 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:ring-brand-900/40';
    $labelClass = 'mb-1 block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
@endphp
<div class="space-y-3 md:space-y-6">
    <h2 class="hidden text-lg font-semibold text-gray-900 dark:text-white md:block">Lương của tôi</h2>

    @if(session('info'))
        <div class="rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-200">{{ session('info') }}</div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <form action="{{ route('food.luong-cua-toi') }}" method="get" class="flex flex-wrap items-end gap-2">
            <div class="min-w-0 flex-1 sm:max-w-[11rem]">
                <label class="{{ $labelClass }}">Tháng</label>
                <input type="month" name="month" value="{{ $month }}" class="{{ $inputClass }}">
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700">Xem</button>
        </form>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <h3 class="mb-2.5 text-sm font-semibold text-gray-900 dark:text-white">Tháng {{ $from->format('m/Y') }}</h3>
        <dl class="grid grid-cols-2 gap-2 sm:grid-cols-3">
            <div class="rounded-lg bg-gray-50 px-2.5 py-1.5 dark:bg-gray-800/80">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Ngày công</dt>
                <dd class="text-sm font-semibold tabular-nums text-gray-900 dark:text-white">{{ $payroll['work_days'] }}</dd>
            </div>
            <div class="rounded-lg bg-gray-50 px-2.5 py-1.5 dark:bg-gray-800/80">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Phút làm</dt>
                <dd class="text-sm font-semibold tabular-nums text-gray-900 dark:text-white">{{ $payroll['work_minutes'] }}</dd>
            </div>
            <div class="rounded-lg bg-gray-50 px-2.5 py-1.5 dark:bg-gray-800/80">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Nghỉ duyệt</dt>
                <dd class="text-sm font-semibold tabular-nums text-gray-900 dark:text-white">{{ $payroll['leave_days_approved'] }}</dd>
            </div>
            <div class="rounded-lg bg-gray-50 px-2.5 py-1.5 dark:bg-gray-800/80">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Hình thức</dt>
                <dd class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ \App\Models\Employee::salaryTypeLabels()[$payroll['salary_type']] ?? $payroll['salary_type'] }}</dd>
            </div>
            <div class="rounded-lg bg-gray-50 px-2.5 py-1.5 dark:bg-gray-800/80">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Mức lương</dt>
                <dd class="text-sm font-semibold tabular-nums text-gray-900 dark:text-white">{{ $fmt($payroll['salary_rate']) }}</dd>
            </div>
            <div class="rounded-lg bg-gray-50 px-2.5 py-1.5 dark:bg-gray-800/80">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Lương gộp</dt>
                <dd class="text-sm font-semibold tabular-nums text-gray-900 dark:text-white">{{ $fmt($payroll['gross_salary']) }}</dd>
            </div>
            <div class="rounded-lg bg-gray-50 px-2.5 py-1.5 dark:bg-gray-800/80">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Phạt trễ</dt>
                <dd class="text-sm font-semibold tabular-nums {{ ($payroll['late_penalty'] ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">
                    @if(($payroll['late_penalty'] ?? 0) > 0)
                        −{{ $fmt($payroll['late_penalty']) }}
                        @if(($payroll['late_minutes'] ?? 0) > 0)
                            <span class="text-[10px] font-normal text-gray-500">({{ $payroll['late_minutes'] }}p)</span>
                        @endif
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div class="rounded-lg bg-brand-50/80 px-2.5 py-1.5 dark:bg-brand-900/20">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-brand-700/80 dark:text-brand-300/80">Thực nhận</dt>
                <dd class="text-sm font-semibold tabular-nums text-brand-700 dark:text-brand-300">{{ $fmt($payroll['net_salary'] ?? $payroll['gross_salary']) }} đ</dd>
            </div>
            <div class="rounded-lg bg-emerald-50/80 px-2.5 py-1.5 dark:bg-emerald-900/20">
                <dt class="text-[10px] font-medium uppercase tracking-wide text-emerald-700/80 dark:text-emerald-300/80">Đã nhận</dt>
                <dd class="text-sm font-semibold tabular-nums text-emerald-800 dark:text-emerald-200">{{ $fmt($totalPaid) }} đ</dd>
            </div>
        </dl>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <h3 class="mb-2.5 text-sm font-semibold text-gray-900 dark:text-white">Lịch sử thanh toán</h3>
        @if($payments->isEmpty())
            <p class="text-xs text-gray-500 dark:text-gray-400">Chưa có khoản thanh toán nào được ghi nhận cho tháng này.</p>
        @else
            <ul class="space-y-1.5">
                @foreach($payments as $pay)
                    <li class="rounded-lg border border-gray-100 px-2.5 py-2 text-sm dark:border-gray-700">
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $paymentTypes[$pay->payment_type] ?? $pay->payment_type }}</span>
                            <span class="shrink-0 text-sm font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">+{{ $fmt($pay->amount) }} đ</span>
                        </div>
                        <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                            {{ $paymentMethods[$pay->payment_method] ?? $pay->payment_method }}
                            · {{ $pay->paid_at?->format('d/m/Y H:i') }}
                        </p>
                        @if($pay->note)
                            <p class="mt-1 text-[11px] leading-relaxed text-gray-600 dark:text-gray-300">{{ $pay->note }}</p>
                        @endif
                        @if($pay->creator)
                            <p class="mt-0.5 text-[10px] text-gray-400">Ghi nhận bởi: {{ $pay->creator->name }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
