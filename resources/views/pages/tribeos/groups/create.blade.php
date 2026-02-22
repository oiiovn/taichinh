@extends('layouts.tribeos')

@section('tribeosContent')
    <div class="space-y-6">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Tạo nhóm</h1>

        @if($errors->any())
            <div class="rounded-lg border border-error-200 bg-error-50 p-3 text-theme-sm text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                <ul class="list-inside list-disc">
                    @foreach($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('tribeos.groups.store') }}" method="post" class="max-w-xl space-y-4">
            @csrf
            <div>
                <label for="name" class="block text-theme-sm font-medium text-gray-700 dark:text-gray-300">Tên nhóm <span class="text-error-500">*</span></label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required
                    class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-theme-sm shadow-theme-xs dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    placeholder="Ví dụ: Hội bạn thân 2025">
            </div>
            <div>
                <label for="description" class="block text-theme-sm font-medium text-gray-700 dark:text-gray-300">Mô tả</label>
                <textarea name="description" id="description" rows="3"
                    class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-theme-sm shadow-theme-xs dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    placeholder="Mô tả ngắn về nhóm (tùy chọn)">{{ old('description') }}</textarea>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">Tạo nhóm</button>
                <a href="{{ route('tribeos.groups.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Hủy</a>
            </div>
        </form>
    </div>
@endsection
