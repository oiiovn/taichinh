@extends('layouts.food')

@section('foodContent')
@php $fmt = fn ($n) => \App\Helpers\BaoCaoHelper::formatGiaVonNguyen($n); @endphp
<div class="space-y-6">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Bảng lương</h2>

    <form action="{{ route('food.luong') }}" method="get" class="flex flex-wrap items-center gap-2">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Tháng:</label>
        <input type="month" name="month" value="{{ $month }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
        <button type="submit" class="rounded-lg bg-brand-600 px-3 py-2 text-sm text-white hover:bg-brand-700">Xem</button>
    </form>

    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full min-w-[640px] text-left text-sm">
            <thead class="border-b border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Nhân viên</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Hình thức</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Số ngày công</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Số giờ (phút)</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Nghỉ (đã duyệt)</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Tổng lương (ước)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    @php $p = $row['payroll']; $emp = $row['employee']; @endphp
                    <tr class="border-b border-gray-100 dark:border-gray-700/50">
                        <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">{{ $emp->user->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ \App\Models\Employee::salaryTypeLabels()[$p['salary_type']] ?? $p['salary_type'] }}</td>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $p['work_days'] }}</td>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $p['work_minutes'] }} phút</td>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $p['leave_days_approved'] }} ngày</td>
                        <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">{{ $fmt($p['gross_salary']) }} đ</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Chưa có nhân viên.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
