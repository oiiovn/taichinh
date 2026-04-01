@extends('layouts.food')

@section('foodContent')
<div class="space-y-6">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Chi nhánh</h2>
    <p class="text-sm text-gray-600 dark:text-gray-400">Tạo chi nhánh trước; khi tải báo cáo bán hàng lên có thể chọn chi nhánh tương ứng.</p>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">{{ session('error') }}</div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
        <h3 class="mb-3 text-sm font-semibold text-gray-800 dark:text-gray-200">Thêm chi nhánh</h3>
        <form action="{{ route('food.chi-nhanh.store') }}" method="POST" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
            @csrf
            <div class="min-w-[200px] flex-1">
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Tên chi nhánh *</label>
                <input type="text" name="name" required maxlength="255" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="VD: Cơ sở 1">
            </div>
            <div class="min-w-[240px] flex-[2]">
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Địa chỉ</label>
                <input type="text" name="address" maxlength="500" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="Số nhà, đường, quận...">
            </div>
            <div class="min-w-[260px] flex-[2]">
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Link chi nhánh</label>
                <input type="url" name="branch_link" maxlength="500" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="https://...">
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Thêm</button>
        </form>
    </div>

    <div class="space-y-4">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Danh sách</h3>
        @forelse($branches as $b)
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                    <form method="POST" action="{{ route('food.chi-nhanh.update', $b) }}" class="flex flex-1 flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                        @csrf
                        @method('PUT')
                        <div class="min-w-[180px] flex-1">
                            <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">Tên</label>
                            <input type="text" name="name" value="{{ $b->name }}" required maxlength="255" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                        </div>
                        <div class="min-w-[220px] flex-[2]">
                            <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">Địa chỉ</label>
                            <input type="text" name="address" value="{{ $b->address }}" maxlength="500" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                        </div>
                        <div class="min-w-[260px] flex-[2]">
                            <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">Link chi nhánh</label>
                            <input type="url" name="branch_link" value="{{ $b->branch_link }}" maxlength="500" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white" placeholder="https://...">
                            @if(!empty($b->branch_link))
                                <a href="{{ $b->branch_link }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-block text-[11px] text-blue-600 hover:underline dark:text-blue-400">Mở link</a>
                            @endif
                        </div>
                        <button type="submit" class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700">Lưu</button>
                    </form>
                    <form action="{{ route('food.chi-nhanh.destroy', $b) }}" method="POST" class="shrink-0" onsubmit="return confirm('Xóa chi nhánh {{ e($b->name) }}?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20 sm:w-auto">Xóa</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="rounded-lg border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">Chưa có chi nhánh. Thêm ít nhất một chi nhánh để gán khi tải báo cáo.</p>
        @endforelse
    </div>
</div>
@endsection
