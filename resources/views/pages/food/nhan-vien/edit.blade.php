@extends('layouts.food')

@section('foodContent')
<div class="space-y-6">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Sửa nhân viên: {{ $employee->user->name ?? '—' }}</h2>

    <form action="{{ route('food.nhan-vien.update', $employee) }}" method="post" class="max-w-xl space-y-4">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tài khoản</label>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $employee->user->name ?? '—' }} ({{ $employee->user->email ?? '—' }})</p>
        </div>
        <div>
            <label for="position" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Chức vụ</label>
            <input type="text" id="position" name="position" value="{{ old('position', $employee->position) }}" class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
        </div>
        <div>
            <label for="salary_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Hình thức lương</label>
            <select id="salary_type" name="salary_type" class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                @foreach($salaryTypeLabels as $val => $label)
                    <option value="{{ $val }}" {{ old('salary_type', $employee->salary_type) == $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="salary_rate" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Mức lương (đ)</label>
            <input type="number" id="salary_rate" name="salary_rate" value="{{ old('salary_rate', $employee->salary_rate) }}" min="0" step="1000" required class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
        </div>
        <div>
            <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ngày bắt đầu</label>
            <input type="date" id="start_date" name="start_date" value="{{ old('start_date', $employee->start_date?->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
        </div>
        <div>
            <label class="flex items-center gap-2">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" value="1" {{ old('active', $employee->active) ? 'checked' : '' }} class="rounded border-gray-300">
                <span class="text-sm text-gray-700 dark:text-gray-300">Đang hoạt động</span>
            </label>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Cập nhật</button>
            <a href="{{ route('food.nhan-vien') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Hủy</a>
        </div>
    </form>
</div>
@endsection
