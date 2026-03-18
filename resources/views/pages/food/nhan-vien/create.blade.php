@extends('layouts.food')

@section('foodContent')
<div class="space-y-6">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Thêm nhân viên</h2>

    <form action="{{ route('food.nhan-vien.store') }}" method="post" class="max-w-xl space-y-4">
        @csrf
        <div>
            <label for="user_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tài khoản (user)</label>
            <select id="user_id" name="user_id" required class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <option value="">— Chọn user —</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ old('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }} ({{ $u->email }})</option>
                @endforeach
            </select>
            @error('user_id')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="position" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Chức vụ</label>
            <input type="text" id="position" name="position" value="{{ old('position') }}" class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="VD: Phục vụ">
        </div>
        <div>
            <label for="salary_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Hình thức lương</label>
            <select id="salary_type" name="salary_type" class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                @foreach($salaryTypeLabels as $val => $label)
                    <option value="{{ $val }}" {{ old('salary_type', 'hour') == $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="salary_rate" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Mức lương (đ/giờ)</label>
            <input type="number" id="salary_rate" name="salary_rate" value="{{ old('salary_rate', 0) }}" min="0" step="1000" required class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="VD: 25000">
            @error('salary_rate')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ngày bắt đầu</label>
            <input type="date" id="start_date" name="start_date" value="{{ old('start_date') }}" class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Thêm</button>
            <a href="{{ route('food.nhan-vien') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Hủy</a>
        </div>
    </form>
</div>
@endsection
