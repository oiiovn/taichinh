@extends('layouts.food')

@section('foodContent')
@php $fmt = fn ($n) => \App\Helpers\BaoCaoHelper::formatGiaVonNguyen($n); @endphp
<div class="space-y-6">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Lương của tôi</h2>

    @if(session('info'))
        <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-200">{{ session('info') }}</div>
    @endif

    <form action="{{ route('food.luong-cua-toi') }}" method="get" class="flex flex-wrap items-center gap-2">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Tháng:</label>
        <input type="month" name="month" value="{{ $month }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
        <button type="submit" class="rounded-lg bg-brand-600 px-3 py-2 text-sm text-white hover:bg-brand-700">Xem</button>
    </form>

    <div class="rounded-xl border border-gray-200 bg-gray-50 p-6 dark:border-gray-700 dark:bg-gray-800/50">
        <h3 class="mb-4 text-sm font-semibold text-gray-800 dark:text-gray-200">Tháng {{ $from->format('m/Y') }}</h3>
        <dl class="grid gap-3 sm:grid-cols-2">
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Số ngày công</dt>
                <dd class="text-lg font-medium text-gray-900 dark:text-white">{{ $payroll['work_days'] }} ngày</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Tổng giờ làm (phút)</dt>
                <dd class="text-lg font-medium text-gray-900 dark:text-white">{{ $payroll['work_minutes'] }} phút</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Số ngày nghỉ (đã duyệt)</dt>
                <dd class="text-lg font-medium text-gray-900 dark:text-white">{{ $payroll['leave_days_approved'] }} ngày</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Hình thức lương</dt>
                <dd class="text-lg font-medium text-gray-900 dark:text-white">{{ \App\Models\Employee::salaryTypeLabels()[$payroll['salary_type']] ?? $payroll['salary_type'] }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Mức lương (đ/giờ hoặc đơn vị)</dt>
                <dd class="text-lg font-medium text-gray-900 dark:text-white">{{ $fmt($payroll['salary_rate']) }} đ</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Tổng lương ước tính</dt>
                <dd class="text-xl font-semibold text-brand-600 dark:text-brand-400">{{ $fmt($payroll['gross_salary']) }} đ</dd>
            </div>
        </dl>
    </div>
</div>
@endsection
