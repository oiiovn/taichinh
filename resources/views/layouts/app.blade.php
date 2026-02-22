<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Trang chủ' }} | Canhan</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Alpine.js -->
    {{-- <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script> --}}

    <script>window.__notificationFlash = @json(session()->pull('notification_flash', false));</script>
    <!-- Theme Store + notification flash (góc chuông flash 5s khi vừa tạo thanh toán/thu nợ) -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('theme', {
                theme: 'light',
                init() {
                    const savedTheme = localStorage.getItem('theme');
                    const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                    this.theme = savedTheme || systemTheme;
                    this.updateTheme();
                },
                toggle() {
                    this.theme = this.theme === 'light' ? 'dark' : 'light';
                    localStorage.setItem('theme', this.theme);
                    this.updateTheme();
                },
                updateTheme() {
                    const html = document.documentElement;
                    if (this.theme === 'dark') {
                        html.classList.add('dark');
                    } else {
                        html.classList.remove('dark');
                    }
                }
            });
            Alpine.store('theme').init();

            Alpine.store('sidebar', {
                isExpanded: false, // mặc định đóng
                isMobileOpen: false,
                isHovered: false,

                toggleExpanded() {
                    this.isExpanded = !this.isExpanded;
                    // When toggling desktop sidebar, ensure mobile menu is closed
                    this.isMobileOpen = false;
                },

                toggleMobileOpen() {
                    this.isMobileOpen = !this.isMobileOpen;
                    // Don't modify isExpanded when toggling mobile menu
                },

                setMobileOpen(val) {
                    this.isMobileOpen = val;
                },

                setHovered(val) {
                    // Only allow hover effects on desktop when sidebar is collapsed
                    if (window.innerWidth >= 1280 && !this.isExpanded) {
                        this.isHovered = val;
                    }
                }
            });

            Alpine.store('notificationFlash', false);
            if (window.__notificationFlash) {
                Alpine.store('notificationFlash', true);
                setTimeout(() => Alpine.store('notificationFlash', false), 5000);
            }
        });
    </script>

    <!-- Áp dụng theme ngay trước khi render (tránh nhấp nháy), đồng bộ với toàn hệ thống -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            const theme = savedTheme || systemTheme;
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>
    
</head>

<body
    x-data="{ 'loaded': true}"
    x-init="$store.sidebar.isExpanded = false;
    const checkMobile = () => {
        if (window.innerWidth < 1280) {
            $store.sidebar.setMobileOpen(false);
        }
        $store.sidebar.isExpanded = false;
    };
    window.addEventListener('resize', checkMobile);">

    {{-- preloader --}}
    <x-common.preloader/>
    {{-- preloader end --}}

    <div class="min-h-screen min-w-0 max-w-full xl:flex overflow-x-hidden">
        @include('layouts.backdrop')
        @include('layouts.sidebar')

        <div class="flex flex-1 min-h-screen min-w-0 flex-col bg-gray-50 transition-all duration-300 ease-in-out dark:bg-gray-900"
            :class="{
                'xl:ml-[290px]': $store.sidebar.isExpanded || $store.sidebar.isHovered,
                'xl:ml-[90px]': !$store.sidebar.isExpanded && !$store.sidebar.isHovered,
                'ml-0': $store.sidebar.isMobileOpen
            }">
            <!-- app header start -->
            @include('layouts.app-header')
            <!-- app header end -->
            <div class="flex min-h-0 min-w-0 flex-1 flex-col @yield('contentWrapperClass', 'p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6')">
                <div class="min-h-0 flex-1 overflow-y-auto pt-16">
                    @yield('content')
                </div>
                @include('layouts.footer')
            </div>
        </div>

    </div>

</body>

@stack('scripts')

</html>
