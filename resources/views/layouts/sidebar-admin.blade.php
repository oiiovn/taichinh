@php
    $currentPath = request()->path();
    $menuItems = [
        ['name' => 'Trang chủ', 'path' => route('admin.index'), 'icon' => 'dashboard'],
        ['name' => 'Quản lý user', 'path' => route('admin.users.index'), 'icon' => 'user-profile'],
        ['name' => 'Thông báo', 'path' => route('admin.broadcasts.index'), 'icon' => 'email'],
        ['name' => 'Lịch sử giao dịch', 'path' => route('admin.lich-su-giao-dich.index'), 'icon' => 'chart-bar'],
        ['name' => 'Hệ thống', 'path' => route('admin.he-thong'), 'icon' => 'settings'],
    ];
@endphp

<aside id="sidebar-admin"
    class="fixed flex flex-col mt-0 top-0 px-5 left-0 bg-white dark:bg-gray-900 dark:border-gray-800 text-gray-900 h-screen transition-all duration-300 ease-in-out z-99999 border-r border-gray-200"
    :class="{
        'w-[290px]': $store.sidebar.isExpanded || $store.sidebar.isMobileOpen || $store.sidebar.isHovered,
        'w-[90px]': !$store.sidebar.isExpanded && !$store.sidebar.isHovered,
        'translate-x-0': $store.sidebar.isMobileOpen,
        '-translate-x-full xl:translate-x-0': !$store.sidebar.isMobileOpen
    }"
    @mouseenter="if (!$store.sidebar.isExpanded) $store.sidebar.setHovered(true)"
    @mouseleave="$store.sidebar.setHovered(false)">
    <div class="pt-8 pb-7 flex"
        :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ? 'xl:justify-center' : 'justify-start'">
        <a href="{{ route('admin.index') }}">
            <img x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                class="dark:hidden" src="/images/logo/logo.svg" alt="Logo" width="150" height="40" />
            <img x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                class="hidden dark:block" src="/images/logo/logo-dark.svg" alt="Logo" width="150" height="40" />
            <img x-show="!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen"
                src="/images/logo/logo-icon.svg" alt="Logo" width="32" height="32" />
        </a>
    </div>
    <div class="flex flex-col overflow-y-auto duration-300 ease-linear no-scrollbar">
        <nav class="mb-6">
            <h2 class="mb-4 text-xs uppercase leading-[20px] text-gray-400"
                :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ? 'lg:justify-center' : 'justify-start'">
                <template x-if="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen">
                    <span>Quản trị</span>
                </template>
            </h2>
            <ul class="flex flex-col gap-1">
                @foreach ($menuItems as $item)
                    @php
                        $isActive = ($item['path'] === route('admin.index') && request()->path() === 'admin')
                            || (str_contains($item['path'], 'users') && request()->is('admin/users*'))
                            || (str_contains($item['path'], 'broadcasts') && request()->is('admin/broadcasts*'))
                            || (str_contains($item['path'], 'lich-su-giao-dich') && request()->is('admin/lich-su-giao-dich*'))
                            || (str_contains($item['path'], 'he-thong') && request()->is('admin/he-thong*'));
                    @endphp
                    <li>
                        <a href="{{ $item['path'] }}" class="menu-item group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors w-full
                            {{ $isActive ? 'menu-item-active bg-primary/10 text-primary dark:bg-primary/20' : 'menu-item-inactive text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5' }}
                            {{ "(!\$store.sidebar.isExpanded && !\$store.sidebar.isHovered && !\$store.sidebar.isMobileOpen) ? 'xl:justify-center' : 'justify-start'" }}">
                            <span class="flex shrink-0 w-6 h-6 [&_svg]:w-6 [&_svg]:h-6 {{ $isActive ? 'menu-item-icon-active' : 'menu-item-icon-inactive' }}">
                                {!! \App\Helpers\MenuHelper::getIconSvg($item['icon']) !!}
                            </span>
                            <span x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen" class="menu-item-text">{{ $item['name'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </div>
</aside>

<div x-show="$store.sidebar.isMobileOpen" @click="$store.sidebar.setMobileOpen(false)" class="fixed z-50 h-screen w-full bg-gray-900/50"></div>
