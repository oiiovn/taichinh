@extends('layouts.food')

@section('foodContent')
@php
    $fmt = fn ($n) => \App\Helpers\BaoCaoHelper::formatGiaVonNguyen($n);
    $inputClass = 'w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-100 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:ring-brand-900/40';
    $labelClass = 'mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400';

    $rows = $rows ?? [];
    $totalEmployees = count($rows);
    $totalWorkDays = collect($rows)->sum(fn ($r) => (float) ($r['payroll']['work_days'] ?? 0));
    $totalNet = collect($rows)->sum(fn ($r) => (float) ($r['payroll']['net_salary'] ?? $r['payroll']['gross_salary'] ?? 0));
    $totalPaid = collect($rows)->sum(fn ($r) => (float) ($r['total_paid'] ?? 0));
    $totalRemaining = $totalNet - $totalPaid;

    $monthLabel = 'Tháng '.(int) $from->format('n').' năm '.$from->format('Y');

    $avatarThemes = [
        ['bg' => 'bg-emerald-100 text-emerald-700', 'ring' => 'ring-emerald-200'],
        ['bg' => 'bg-sky-100 text-sky-700', 'ring' => 'ring-sky-200'],
        ['bg' => 'bg-violet-100 text-violet-700', 'ring' => 'ring-violet-200'],
        ['bg' => 'bg-amber-100 text-amber-700', 'ring' => 'ring-amber-200'],
        ['bg' => 'bg-rose-100 text-rose-700', 'ring' => 'ring-rose-200'],
        ['bg' => 'bg-teal-100 text-teal-700', 'ring' => 'ring-teal-200'],
    ];

    $initials = function (?string $name): string {
        $name = trim((string) $name);
        if ($name === '') {
            return 'NV';
        }
        $parts = preg_split('/\s+/u', $name) ?: [];
        if (count($parts) >= 2) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1).mb_substr($parts[count($parts) - 1], 0, 1));
        }

        return mb_strtoupper(mb_substr($name, 0, 2));
    };
@endphp
@php $paymentFormOpen = $errors->any() || old('employee_id') || old('amount'); @endphp
<div class="space-y-5" @if($canRecordPayment ?? false) x-data="{ payOpen: {{ $paymentFormOpen ? 'true' : 'false' }}, editPayOpen: false, editPay: null }" @endif>
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Bảng lương</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Tổng hợp lương và chi tiết trả cho nhân viên</p>
        </div>
        @if($canRecordPayment ?? false)
            <button type="button"
                @click="payOpen = !payOpen"
                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Ghi nhận trả lương
            </button>
        @endif
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">{{ session('error') }}</div>
    @endif

    {{-- Filter + summary cards --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-6">
        <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 xl:col-span-1">
            <form action="{{ route('food.luong') }}" method="get" class="space-y-3">
                <div>
                    <label class="{{ $labelClass }}">Tháng</label>
                    <div class="relative">
                        <input type="month" name="month" value="{{ $month }}" class="{{ $inputClass }} pr-10">
                        <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">{{ $monthLabel }}</p>
                </div>
                <button type="submit" class="w-full rounded-xl bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700">Xem</button>
            </form>
        </div>

        <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </span>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Tổng nhân viên</p>
                    <p class="mt-0.5 text-lg font-bold text-gray-900 dark:text-white">{{ $totalEmployees }} <span class="text-sm font-semibold text-gray-500">nhân viên</span></p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </span>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Tổng ngày công</p>
                    <p class="mt-0.5 text-lg font-bold text-gray-900 dark:text-white">{{ number_format($totalWorkDays, 0, ',', '.') }} <span class="text-sm font-semibold text-gray-500">ngày</span></p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                </span>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Tổng thực nhận</p>
                    <p class="mt-0.5 text-lg font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{{ $fmt($totalNet) }} đ</p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Tổng đã trả</p>
                    <p class="mt-0.5 text-lg font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{{ $fmt($totalPaid) }} đ</p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Tổng còn lại</p>
                    <p class="mt-0.5 text-lg font-bold tabular-nums text-amber-600 dark:text-amber-400">{{ $fmt($totalRemaining) }} đ</p>
                </div>
            </div>
        </div>
    </div>

    @if($canRecordPayment ?? false)
        <div x-show="payOpen" x-cloak x-transition class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                <h3 class="text-base font-bold text-gray-900 dark:text-white">Ghi nhận đã trả lương</h3>
                <button type="button" @click="payOpen = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form action="{{ route('food.luong.store-payment') }}" method="post" class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">
                <div>
                    <label class="{{ $labelClass }}">Nhân viên</label>
                    <select name="employee_id" required class="{{ $inputClass }}">
                        <option value="">— Chọn —</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}" @selected((string) old('employee_id') === (string) $emp->id)>{{ $emp->user->name ?? 'NV #'.$emp->id }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Loại thanh toán</label>
                    <select name="payment_type" required class="{{ $inputClass }}">
                        @foreach($paymentTypes as $key => $label)
                            <option value="{{ $key }}" @selected(old('payment_type') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Hình thức</label>
                    <select name="payment_method" required class="{{ $inputClass }}">
                        @foreach($paymentMethods as $key => $label)
                            <option value="{{ $key }}" @selected(old('payment_method') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Số tiền (đ)</label>
                    <input type="number" name="amount" min="1" step="1" required value="{{ old('amount') }}" placeholder="Số tiền (đồng)" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Ngày thanh toán</label>
                    <input type="date" name="paid_at" value="{{ old('paid_at', now()->format('Y-m-d')) }}" class="{{ $inputClass }}">
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <label class="{{ $labelClass }}">Nội dung / ghi chú</label>
                    <input type="text" name="note" maxlength="1000" value="{{ old('note') }}" placeholder="Ví dụ: Lương tháng {{ $from->format('m/Y') }}" class="{{ $inputClass }}">
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <button type="submit" class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">Ghi nhận thanh toán</button>
                </div>
            </form>
        </div>
    @endif

    {{-- Mobile cards --}}
    <div class="space-y-3 lg:hidden">
        @forelse($rows as $row)
            @php
                $p = $row['payroll'];
                $emp = $row['employee'];
                $name = $emp->user->name ?? '—';
                $theme = $avatarThemes[$loop->index % count($avatarThemes)];
                $net = (float) ($p['net_salary'] ?? $p['gross_salary'] ?? 0);
                $paid = (float) ($row['total_paid'] ?? 0);
                $remaining = $net - $paid;
            @endphp
            <article class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-start gap-3 p-4">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-bold ring-2 {{ $theme['bg'] }} {{ $theme['ring'] }}">{{ $initials($name) }}</span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h3 class="font-bold text-gray-900 dark:text-white">{{ $name }}</h3>
                                <p class="text-xs text-gray-500">Nhân viên</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">{{ \App\Models\Employee::salaryTypeLabels()[$p['salary_type']] ?? $p['salary_type'] }}</span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                            <div><span class="text-gray-500">Ngày công:</span> <strong>{{ $p['work_days'] }}</strong></div>
                            <div><span class="text-gray-500">Thực nhận:</span> <strong>{{ $fmt($net) }} đ</strong></div>
                            <div><span class="text-gray-500">Đã trả:</span> <strong class="text-emerald-600">{{ $fmt($paid) }} đ</strong></div>
                            <div><span class="text-gray-500">Còn lại:</span> <strong class="{{ $remaining > 0 ? 'text-amber-600' : 'text-blue-600' }}">{{ $fmt($remaining) }} đ</strong></div>
                        </div>
                    </div>
                </div>
                @if($row['payments']->isNotEmpty())
                    <div class="space-y-2 border-t border-gray-100 px-4 py-3 dark:border-gray-800">
                        @foreach($row['payments'] as $pay)
                            @include('pages.food.luong.partials.payment-detail-row', ['pay' => $pay, 'fmt' => $fmt, 'paymentTypes' => $paymentTypes, 'paymentMethods' => $paymentMethods, 'canRecordPayment' => $canRecordPayment ?? false, 'month' => $month, 'idPrefix' => 'm-'.$pay->id])
                        @endforeach
                    </div>
                @else
                    <div class="border-t border-gray-100 px-4 py-3 dark:border-gray-800">
                        <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs text-gray-500 dark:bg-gray-800">Chưa ghi nhận</span>
                    </div>
                @endif
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-10 text-center dark:border-gray-700 dark:bg-gray-900/50">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Chưa có nhân viên.</p>
            </div>
        @endforelse
    </div>

    {{-- Desktop table --}}
    <div class="hidden overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900 lg:block">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1100px] text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/90 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-800/60 dark:text-gray-400">
                        <th class="px-5 py-3.5">Nhân viên</th>
                        <th class="px-4 py-3.5">Hình thức</th>
                        <th class="px-4 py-3.5 text-center">Số ngày công</th>
                        <th class="px-4 py-3.5 text-right">Lương gộp</th>
                        <th class="px-4 py-3.5 text-right">Phạt đi trễ</th>
                        <th class="px-4 py-3.5 text-right">Thực nhận (ước)</th>
                        <th class="px-4 py-3.5 text-right">Đã trả (tháng)</th>
                        <th class="px-4 py-3.5 text-right">Còn lại</th>
                        <th class="min-w-[240px] px-4 py-3.5">Chi tiết đã trả</th>
                        <th class="w-10 px-2 py-3.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($rows as $row)
                        @php
                            $p = $row['payroll'];
                            $emp = $row['employee'];
                            $name = $emp->user->name ?? '—';
                            $theme = $avatarThemes[$loop->index % count($avatarThemes)];
                            $net = (float) ($p['net_salary'] ?? $p['gross_salary'] ?? 0);
                            $paid = (float) ($row['total_paid'] ?? 0);
                            $remaining = $net - $paid;
                            $hasPenalty = ($p['late_penalty'] ?? 0) > 0;
                        @endphp
                        <tr class="transition hover:bg-gray-50/60 dark:hover:bg-gray-800/30">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-xs font-bold ring-2 {{ $theme['bg'] }} {{ $theme['ring'] }}">{{ $initials($name) }}</span>
                                    <div>
                                        <p class="font-bold text-gray-900 dark:text-white">{{ $name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Nhân viên</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <span class="inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">{{ \App\Models\Employee::salaryTypeLabels()[$p['salary_type']] ?? $p['salary_type'] }}</span>
                            </td>
                            <td class="px-4 py-4 text-center tabular-nums text-gray-800 dark:text-gray-200">{{ $p['work_days'] }} <span class="text-gray-400">ngày</span></td>
                            <td class="px-4 py-4 text-right tabular-nums text-gray-900 dark:text-white">{{ $fmt($p['gross_salary']) }} đ</td>
                            <td class="px-4 py-4 text-right tabular-nums">
                                @if($hasPenalty)
                                    <span class="font-semibold text-red-600 dark:text-red-400">−{{ $fmt($p['late_penalty']) }} đ</span>
                                    @if(($p['late_minutes'] ?? 0) > 0)
                                        <span class="mt-0.5 block text-xs font-normal text-red-500/80">({{ $p['late_minutes'] }} phút)</span>
                                    @endif
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-right text-base font-bold tabular-nums text-gray-900 dark:text-white">{{ $fmt($net) }} đ</td>
                            <td class="px-4 py-4 text-right text-base font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{{ $fmt($paid) }} đ</td>
                            <td class="px-4 py-4 text-right text-base font-bold tabular-nums">
                                @if($remaining > 0)
                                    <span class="text-amber-600 dark:text-amber-400">{{ $fmt($remaining) }} đ</span>
                                @elseif($remaining < 0)
                                    <span class="text-blue-600 dark:text-blue-400">{{ $fmt($remaining) }} đ</span>
                                @else
                                    <span class="text-blue-600 dark:text-blue-400">0 đ</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 align-top">
                                @if($row['payments']->isEmpty())
                                    <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs text-gray-500 dark:bg-gray-800 dark:text-gray-400">Chưa ghi nhận</span>
                                @else
                                    <div class="space-y-2.5">
                                        @foreach($row['payments'] as $pay)
                                            @include('pages.food.luong.partials.payment-detail-row', ['pay' => $pay, 'fmt' => $fmt, 'paymentTypes' => $paymentTypes, 'paymentMethods' => $paymentMethods, 'canRecordPayment' => $canRecordPayment ?? false, 'month' => $month, 'idPrefix' => $pay->id])
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="px-2 py-4 text-center text-gray-300 dark:text-gray-600">
                                <svg class="mx-auto h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-5 py-12 text-center text-gray-500 dark:text-gray-400">Chưa có nhân viên.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-col gap-3 border-t border-gray-100 px-5 py-4 text-sm dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-gray-500 dark:text-gray-400">
                Hiển thị <span class="font-medium text-gray-700 dark:text-gray-300">1</span> đến <span class="font-medium text-gray-700 dark:text-gray-300">{{ $totalEmployees }}</span> trong tổng số <span class="font-medium text-gray-700 dark:text-gray-300">{{ $totalEmployees }}</span> nhân viên
            </p>
            <div class="flex flex-wrap items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-emerald-500"></span> Đã trả đủ</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-amber-500"></span> Còn nợ</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-blue-500"></span> Chưa trả</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-red-500"></span> Phạt</span>
            </div>
        </div>
    </div>

    @if($canRecordPayment ?? false)
        <div x-show="editPayOpen" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4" @keydown.escape.window="editPayOpen = false">
            <div x-show="editPayOpen" x-transition class="w-full max-w-md rounded-t-2xl border border-gray-200 bg-white p-5 shadow-xl dark:border-gray-700 dark:bg-gray-800 sm:rounded-2xl sm:p-6" @click.stop>
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Sửa chi tiết trả lương</h3>
                    <button type="button" @click="editPayOpen = false" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700">✕</button>
                </div>
                <template x-if="editPay">
                    <form :action="'{{ url('/food/luong/thanh-toan') }}/' + editPay.id" method="POST">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="month" value="{{ $month }}">
                        <div class="space-y-3">
                            <div>
                                <label class="{{ $labelClass }}">Loại thanh toán</label>
                                <select name="payment_type" x-model="editPay.payment_type" required class="{{ $inputClass }}">
                                    @foreach($paymentTypes as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">Hình thức</label>
                                <select name="payment_method" x-model="editPay.payment_method" required class="{{ $inputClass }}">
                                    @foreach($paymentMethods as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">Số tiền (đ)</label>
                                <input type="number" name="amount" min="1" step="1" required x-model="editPay.amount" class="{{ $inputClass }}">
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">Ngày thanh toán</label>
                                <input type="date" name="paid_at" required x-model="editPay.paid_at" class="{{ $inputClass }}">
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">Nội dung / ghi chú</label>
                                <input type="text" name="note" maxlength="1000" x-model="editPay.note" class="{{ $inputClass }}" placeholder="Tùy chọn">
                            </div>
                        </div>
                        <div class="mt-5 flex gap-2">
                            <button type="submit" class="flex-1 rounded-xl bg-brand-600 px-4 py-3 text-sm font-semibold text-white hover:bg-brand-700">Lưu</button>
                            <button type="button" @click="editPayOpen = false" class="rounded-xl border border-gray-300 px-4 py-3 text-sm font-medium dark:border-gray-600 dark:text-gray-300">Hủy</button>
                        </div>
                    </form>
                </template>
                <template x-if="editPay">
                    <form id="form-delete-pay-edit" :action="'{{ url('/food/luong/thanh-toan') }}/' + editPay.id" method="POST" class="mt-3">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="month" value="{{ $month }}">
                        <button type="button"
                            @click="$dispatch('confirm-delete-open', { formId: 'form-delete-pay-edit', message: 'Xóa bản ghi trả lương này?' })"
                            class="w-full rounded-xl border border-red-200 px-4 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-900/20">Xóa bản ghi này</button>
                    </form>
                </template>
            </div>
        </div>
    @endif
</div>
@endsection
