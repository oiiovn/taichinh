@extends('layouts.food')

@section('foodContent')
<div class="space-y-4">
    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">{{ session('error') }}</div>
    @endif

    <div class="flex items-center justify-between gap-3">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Nhập đánh giá</h2>
        <a href="{{ route('food.reviews.index') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">Mở danh sách đánh giá</a>
    </div>

    <form method="POST" action="{{ route('food.reviews.import-text') }}" class="rounded-xl border border-blue-200 bg-blue-50/60 p-4 dark:border-blue-700 dark:bg-blue-900/20">
        @csrf
        <div class="mb-3">
            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Chi nhánh (tuỳ chọn)</label>
            <select name="food_branch_id" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <option value="">Không gán chi nhánh</option>
                @foreach($branches as $br)
                    <option value="{{ $br->id }}" @selected((int) old('food_branch_id', $branchId) === (int) $br->id)>{{ $br->name }}</option>
                @endforeach
            </select>
        </div>
        <p class="mb-2 text-sm font-semibold text-gray-800 dark:text-gray-200">Dán text từ file .rtfd</p>
        <p class="mb-3 text-xs text-gray-600 dark:text-gray-400">Hệ thống tách theo mã <span class="font-mono">#xxxx-xxxx</span> và chống trùng theo chính mã đó.</p>
        <textarea name="reviews_text" rows="16" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="Dán nội dung đánh giá...">{{ old('reviews_text') }}</textarea>
        @error('reviews_text')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700 sm:w-auto sm:rounded-lg sm:py-2">Nhập đánh giá</button>
            <a href="{{ route('food.reviews.index') }}" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 sm:hidden">← Danh sách</a>
        </div>
    </form>
</div>
@endsection

