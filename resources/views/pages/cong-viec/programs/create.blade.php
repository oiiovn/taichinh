@extends('layouts.cong-viec')

@section('congViecContent')
<div class="mx-auto max-w-lg">
    <h1 class="mb-6 text-2xl font-bold text-gray-900 dark:text-white">Tạo chương trình</h1>
    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Chương trình = tập hợp mục tiêu + thời hạn + quy tắc (ví dụ: 30 ngày kỷ luật dọn bàn). Sau khi tạo, bạn gắn công việc vào chương trình từ trang Công việc.</p>
    <form action="{{ route('cong-viec.programs.store') }}" method="POST" class="space-y-4">
        @csrf
        <div>
            <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Bạn muốn rèn điều gì?</label>
            <input type="text" name="title" id="title" value="{{ old('title') }}" required maxlength="200"
                class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Mô tả (tùy chọn)</label>
            <textarea name="description" id="description" rows="2" maxlength="2000" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">{{ old('description') }}</textarea>
        </div>
        <div>
            <label for="duration_days" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Bao lâu? (số ngày)</label>
            <input type="number" name="duration_days" id="duration_days" value="{{ old('duration_days', 30) }}" min="1" max="365"
                class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            @error('duration_days')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="daily_target_count" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Mỗi ngày bao nhiêu? (số lần/task đạt target, mặc định 1)</label>
            <input type="number" name="daily_target_count" id="daily_target_count" value="{{ old('daily_target_count', 1) }}" min="1" max="20"
                class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
        </div>
        <div>
            <label for="skip_policy" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nếu lỡ 1 ngày thì sao?</label>
            <select name="skip_policy" id="skip_policy" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <option value="abandon" {{ old('skip_policy') === 'abandon' ? 'selected' : '' }}>Bỏ luôn (fail ngay)</option>
                <option value="allow_2_skips" {{ old('skip_policy', 'allow_2_skips') === 'allow_2_skips' ? 'selected' : '' }}>Cho phép tối đa 2 ngày bỏ</option>
                <option value="reduce_difficulty" {{ old('skip_policy') === 'reduce_difficulty' ? 'selected' : '' }}>Tự giảm độ khó khi lỡ</option>
            </select>
        </div>
        <div class="flex gap-2 pt-2">
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Tạo chương trình</button>
            <a href="{{ route('cong-viec.programs.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">Hủy</a>
        </div>
    </form>
</div>
@endsection
