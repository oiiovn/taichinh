@php
    $currentYear = date('Y');
@endphp
<footer class="mt-auto bg-white dark:bg-gray-900">
    <div class="mx-auto flex min-w-0 max-w-(--breakpoint-2xl) flex-col items-center justify-between gap-4 px-4 py-5 md:flex-row md:px-6">
        <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-1 text-sm text-gray-600 dark:text-gray-400">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-600 dark:hover:text-brand-400">Trang chủ</a>
            <a href="{{ route('admin.index') }}" class="hover:text-brand-600 dark:hover:text-brand-400">Quản trị</a>
        </div>
        <p class="text-center text-sm text-gray-500 dark:text-gray-500">© {{ $currentYear }} Canhan. Bảo lưu mọi quyền.</p>
    </div>
</footer>
