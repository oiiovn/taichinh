@extends('layouts.app')

@section('contentWrapperClass')
    w-full p-0 overflow-x-hidden
@endsection

@section('content')
    @php
        $navItems = [
            ['id' => 'trang-chu', 'icon' => 'dashboard', 'label' => 'Trang chủ', 'path' => route('tribeos')],
            ['id' => 'nhom', 'icon' => 'tribe', 'label' => 'Nhóm', 'path' => route('tribeos.groups.index')],
            ['id' => 'thanh-vien', 'icon' => 'user-profile', 'label' => 'Bạn bè', 'path' => '#'],
            ['id' => 'thong-bao', 'icon' => 'email', 'label' => 'Lời mời', 'path' => route('tribeos.invitations.index')],
        ];
        $tribeosSidebarGroups = auth()->check() ? auth()->user()->tribeosGroups()->orderByPivot('created_at', 'desc')->get() : collect();
    @endphp

    <div class="min-h-screen">
        <div class="flex justify-center gap-0 lg:gap-4 xl:gap-6">
            {{-- Sidebar trái kiểu Facebook --}}
            <nav class="hidden lg:flex lg:w-[280px] xl:w-[320px] shrink-0 flex-col pt-4 pb-6 px-2">
                <ul class="space-y-1">
                    @foreach($navItems as $item)
                        @php
                            $isActive = request()->url() === $item['path'] || (request()->routeIs('tribeos') && $item['id'] === 'trang-chu') || (request()->routeIs('tribeos.groups.*') && $item['id'] === 'nhom') || (request()->routeIs('tribeos.invitations.*') && $item['id'] === 'thong-bao');
                        @endphp
                        <li>
                            <a href="{{ $item['path'] }}"
                                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-[15px] font-medium transition-colors {{ $isActive ? 'bg-gray-200 dark:bg-white/10 text-[#1877F2]' : 'text-gray-700 hover:bg-gray-200/80 dark:text-gray-200 dark:hover:bg-white/5' }}">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center [&_svg]:h-7 [&_svg]:w-7 {{ $isActive ? 'text-[#1877F2]' : 'text-gray-600 dark:text-gray-400' }}">{!! \App\Helpers\MenuHelper::getIconSvg($item['icon']) !!}</span>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            {{-- Cột giữa: Newfeed --}}
            <main class="w-full min-w-0 max-w-[680px] shrink-0 px-2 sm:px-4 py-4">
                <div class="min-h-[60vh] overflow-hidden">
                    @yield('tribeosContent')
                </div>
            </main>

            {{-- Cột phải: Nhóm của bạn --}}
            <aside class="hidden xl:block w-[300px] shrink-0 pt-4 pb-6 space-y-4">
                <div class="p-4">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Nhóm của bạn</h3>
                    @if($tribeosSidebarGroups->isEmpty())
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Chưa tham gia nhóm nào.</p>
                        <a href="{{ route('tribeos.groups.index') }}" class="mt-2 inline-block text-sm font-medium text-[#1877F2] hover:underline">Nhóm của tôi →</a>
                    @else
                        <ul class="mt-3 space-y-1">
                            @foreach($tribeosSidebarGroups->take(8) as $g)
                                <li>
                                    <a href="{{ route('tribeos.groups.show', $g->slug) }}" class="flex items-center gap-2 rounded-lg px-2 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#1877F2]/20 text-[#1877F2] text-xs font-semibold">{{ mb_substr($g->name, 0, 1) }}</span>
                                        <span class="truncate">{{ $g->name }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ route('tribeos.groups.index') }}" class="mt-2 inline-block text-sm font-medium text-[#1877F2] hover:underline">Xem tất cả</a>
                    @endif
                </div>
                <div class="p-4">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Thành viên đang hoạt động</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Sẽ hiển thị sau.</p>
                </div>
            </aside>
        </div>
    </div>
@endsection
