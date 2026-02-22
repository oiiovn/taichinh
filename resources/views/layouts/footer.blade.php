@php
    $footerNavItems = auth()->check() ? \App\Helpers\MenuHelper::getMainNavItems() : [];
    $currentYear = date('Y');
@endphp
<footer class="mt-auto border-t border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
    <div class="mx-auto flex min-w-0 max-w-(--breakpoint-2xl) flex-col items-center justify-between gap-4 px-4 py-5 md:flex-row md:px-6">
        <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-1 text-sm text-gray-600 dark:text-gray-400">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-600 dark:hover:text-brand-400">Trang chủ</a>
            @foreach ($footerNavItems as $item)
                <a href="{{ url($item['path']) }}" class="hover:text-brand-600 dark:hover:text-brand-400">{{ $item['name'] }}</a>
            @endforeach
            @auth
                <a href="{{ route('profile') }}" class="hover:text-brand-600 dark:hover:text-brand-400">Hồ sơ</a>
            @endauth
        </div>
        <p class="text-center text-sm text-gray-500 dark:text-gray-500">© {{ $currentYear }} Canhan. Bảo lưu mọi quyền.</p>
    </div>
</footer>
