@extends('layouts.food')

@section('foodContent')
@php
    $fmt = fn ($n) => \App\Helpers\BaoCaoHelper::formatGiaVonNguyen($n);
    $inputClass = 'w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-100 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:ring-brand-900/40';
    $labelClass = 'mb-1 block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
@endphp
@php $paymentFormOpen = $errors->any() || old('employee_id') || old('amount'); @endphp
<div class="space-y-3 md:space-y-6" @if($canRecordPayment ?? false) x-data="{ payOpen: {{ $paymentFormOpen ? 'true' : 'false' }} }" @endif>
    <div class="flex items-center justify-between gap-3">
        <h2 class="hidden text-lg font-semibold text-gray-900 dark:text-white md:block">Bảng lương</h2>
        @if($canRecordPayment ?? false)
            <button type="button"
                @click="payOpen = !payOpen"
                class="ml-auto inline-flex shrink-0 items-center gap-1.5 rounded-full bg-brand-600 px-3.5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700 active:scale-[0.98]">
                <svg class="h-4 w-4 transition-transform" :class="payOpen && 'rotate-45'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span x-text="payOpen ? 'Đóng' : 'Ghi nhận trả lương'"></span>
            </button>
        @endif
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">{{ session('error') }}</div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <form action="{{ route('food.luong') }}" method="get" class="flex flex-wrap items-end gap-2">
            <div class="min-w-0 flex-1 sm:max-w-[11rem]">
                <label class="{{ $labelClass }}">Tháng</label>
                <input type="month" name="month" value="{{ $month }}" class="{{ $inputClass }}">
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700">Xem</button>
        </form>
    </div>

    @if($canRecordPayment ?? false)
        <div x-show="payOpen" x-cloak x-transition class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-2.5 flex items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Ghi nhận đã trả lương</h3>
                <button type="button" @click="payOpen = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form action="{{ route('food.luong.store-payment') }}" method="post" class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
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
                    <button type="submit" class="w-full rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700 sm:w-auto">Ghi nhận thanh toán</button>
                </div>
            </form>
        </div>
    @endif

    {{-- Mobile cards --}}
    <div class="space-y-2 md:hidden">
        @forelse($rows as $row)
            @php
                $p = $row['payroll'];
                $emp = $row['employee'];
                $net = (float) ($p['net_salary'] ?? $p['gross_salary'] ?? 0);
                $paid = (float) ($row['total_paid'] ?? 0);
                $remaining = $net - $paid;
            @endphp
            <article class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-start justify-between gap-2 px-3 pt-2.5">
                    <div class="min-w-0">
                        <h3 class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $emp->user->name ?? '—' }}</h3>
                        <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">{{ \App\Models\Employee::salaryTypeLabels()[$p['salary_type']] ?? $p['salary_type'] }} · {{ $p['work_days'] }} ngày công</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Thực nhận</p>
                        <p class="text-sm font-semibold tabular-nums text-gray-950 dark:text-white">{{ $fmt($net) }} đ</p>
                    </div>
                </div>
                <div class="mt-2 grid grid-cols-2 gap-1.5 px-3 sm:grid-cols-4">
                    <div class="rounded-lg bg-gray-50 px-2 py-1.5 dark:bg-gray-800/80">
                        <p class="text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Gộp</p>
                        <p class="text-xs font-semibold tabular-nums text-gray-900 dark:text-white">{{ $fmt($p['gross_salary']) }}</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-2 py-1.5 dark:bg-gray-800/80">
                        <p class="text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Phạt</p>
                        <p class="text-xs font-semibold tabular-nums {{ ($p['late_penalty'] ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">
                            @if(($p['late_penalty'] ?? 0) > 0)−{{ $fmt($p['late_penalty']) }}@else —@endif
                        </p>
                    </div>
                    <div class="rounded-lg bg-emerald-50/80 px-2 py-1.5 dark:bg-emerald-900/20">
                        <p class="text-[10px] font-medium uppercase tracking-wide text-emerald-700/80 dark:text-emerald-300/80">Đã trả</p>
                        <p class="text-xs font-semibold tabular-nums text-emerald-800 dark:text-emerald-200">{{ $fmt($paid) }}</p>
                    </div>
                    <div class="rounded-lg {{ $remaining > 0 ? 'bg-amber-50/80 dark:bg-amber-900/20' : 'bg-gray-50 dark:bg-gray-800/80' }} px-2 py-1.5">
                        <p class="text-[10px] font-medium uppercase tracking-wide {{ $remaining > 0 ? 'text-amber-700/80 dark:text-amber-300/80' : 'text-gray-500 dark:text-gray-400' }}">Còn lại</p>
                        <p class="text-xs font-semibold tabular-nums {{ $remaining > 0 ? 'text-amber-800 dark:text-amber-200' : ($remaining < 0 ? 'text-blue-700 dark:text-blue-300' : 'text-gray-900 dark:text-white') }}">{{ $fmt($remaining) }}</p>
                    </div>
                </div>
                @if($row['payments']->isNotEmpty())
                    <div class="mt-2 space-y-1 border-t border-gray-100 px-3 py-2 dark:border-gray-800">
                        @foreach($row['payments'] as $pay)
                            <div class="flex items-start justify-between gap-2 text-[11px]">
                                <div class="min-w-0">
                                    <span class="font-medium text-gray-800 dark:text-gray-200">{{ $paymentTypes[$pay->payment_type] ?? $pay->payment_type }}</span>
                                    <span class="text-gray-500"> · {{ $paymentMethods[$pay->payment_method] ?? $pay->payment_method }}</span>
                                    <span class="block text-gray-400">{{ $pay->paid_at?->format('d/m H:i') }}</span>
                                </div>
                                <span class="shrink-0 font-semibold tabular-nums text-gray-900 dark:text-white">{{ $fmt($pay->amount) }} đ</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="px-3 pb-2.5 pt-1.5 text-[11px] text-gray-400">Chưa ghi nhận thanh toán</p>
                @endif
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-3 py-8 text-center dark:border-gray-700 dark:bg-gray-900/50">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Chưa có nhân viên.</p>
            </div>
        @endforelse
    </div>

    {{-- Desktop table --}}
    <div class="hidden overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700 md:block">
        <table class="w-full min-w-[900px] text-left text-sm">
            <thead class="border-b border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Nhân viên</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Hình thức</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Số ngày công</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Lương gộp</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Phạt đi trễ</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Thực nhận (ước)</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Đã trả (tháng)</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Còn lại</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Chi tiết đã trả</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    @php
                        $p = $row['payroll'];
                        $emp = $row['employee'];
                        $net = (float) ($p['net_salary'] ?? $p['gross_salary'] ?? 0);
                        $paid = (float) ($row['total_paid'] ?? 0);
                        $remaining = $net - $paid;
                    @endphp
                    <tr class="border-b border-gray-100 align-top dark:border-gray-700/50">
                        <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">{{ $emp->user->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ \App\Models\Employee::salaryTypeLabels()[$p['salary_type']] ?? $p['salary_type'] }}</td>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $p['work_days'] }}</td>
                        <td class="px-4 py-2 text-gray-900 dark:text-white">{{ $fmt($p['gross_salary']) }} đ</td>
                        <td class="px-4 py-2 {{ ($p['late_penalty'] ?? 0) > 0 ? 'text-red-600 dark:text-red-400 font-medium' : 'text-gray-700 dark:text-gray-300' }}">
                            @if(($p['late_penalty'] ?? 0) > 0)
                                −{{ $fmt($p['late_penalty']) }} đ
                                @if(($p['late_minutes'] ?? 0) > 0)
                                    <span class="block text-xs font-normal text-gray-500">({{ $p['late_minutes'] }} phút)</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">{{ $fmt($net) }} đ</td>
                        <td class="px-4 py-2 font-semibold text-green-600 dark:text-green-400">{{ $fmt($paid) }} đ</td>
                        <td class="px-4 py-2 font-semibold {{ $remaining > 0 ? 'text-amber-700 dark:text-amber-300' : ($remaining < 0 ? 'text-blue-700 dark:text-blue-300' : 'text-gray-700 dark:text-gray-300') }}">{{ $fmt($remaining) }} đ</td>
                        <td class="px-4 py-2 text-xs text-gray-600 dark:text-gray-400">
                            @forelse($row['payments'] as $pay)
                                <div class="mb-1 rounded border border-gray-100 p-2 dark:border-gray-700">
                                    <span class="font-medium text-gray-800 dark:text-gray-200">{{ $paymentTypes[$pay->payment_type] ?? $pay->payment_type }}</span>
                                    — {{ $fmt($pay->amount) }} đ
                                    ({{ $paymentMethods[$pay->payment_method] ?? $pay->payment_method }})
                                    <span class="block text-gray-500">{{ $pay->paid_at?->format('d/m/Y H:i') }}</span>
                                    @if($pay->note)<span class="block">{{ $pay->note }}</span>@endif
                                    @if($pay->creator)<span class="block text-gray-400">Ghi bởi: {{ $pay->creator->name }}</span>@endif
                                </div>
                            @empty
                                <span class="text-gray-400">Chưa ghi nhận</span>
                            @endforelse
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Chưa có nhân viên.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
