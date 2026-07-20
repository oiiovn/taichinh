@extends('layouts.food')

@section('foodContent')
@php $fmt = fn ($n) => \App\Helpers\BaoCaoHelper::formatGiaVonNguyen($n); @endphp
<div class="space-y-6">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Bảng lương</h2>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">{{ session('error') }}</div>
    @endif

    <form action="{{ route('food.luong') }}" method="get" class="flex flex-wrap items-center gap-2">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Tháng:</label>
        <input type="month" name="month" value="{{ $month }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
        <button type="submit" class="rounded-lg bg-brand-600 px-3 py-2 text-sm text-white hover:bg-brand-700">Xem</button>
    </form>

    @if($canRecordPayment ?? false)
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <h3 class="mb-3 text-sm font-semibold text-gray-800 dark:text-gray-200">Ghi nhận đã trả lương</h3>
            <form action="{{ route('food.luong.store-payment') }}" method="post" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Nhân viên</label>
                    <select name="employee_id" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="">— Chọn —</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->user->name ?? 'NV #'.$emp->id }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Loại thanh toán</label>
                    <select name="payment_type" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        @foreach($paymentTypes as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Hình thức</label>
                    <select name="payment_method" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        @foreach($paymentMethods as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Số tiền (đ)</label>
                    <input type="number" name="amount" min="1000" step="1000" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Ngày thanh toán</label>
                    <input type="date" name="paid_at" value="{{ now()->format('Y-m-d') }}" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Nội dung / ghi chú</label>
                    <input type="text" name="note" maxlength="1000" placeholder="Ví dụ: Lương tháng {{ $from->format('m/Y') }}" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Ghi nhận thanh toán</button>
                </div>
            </form>
        </div>
    @endif

    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
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
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Chi tiết đã trả</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    @php $p = $row['payroll']; $emp = $row['employee']; @endphp
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
                        <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">{{ $fmt($p['net_salary'] ?? $p['gross_salary']) }} đ</td>
                        <td class="px-4 py-2 font-semibold text-green-600 dark:text-green-400">{{ $fmt($row['total_paid']) }} đ</td>
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
                        <td colspan="8" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Chưa có nhân viên.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
