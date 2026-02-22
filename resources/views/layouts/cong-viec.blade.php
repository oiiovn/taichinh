@extends('layouts.app')

@section('contentWrapperClass')
    w-full p-4 md:p-6
@endsection

@section('content')
    @php
        $validTabs = ['tong-quan', 'hom-nay', 'du-kien', 'hoan-thanh'];
        $currentTab = in_array(request('tab'), $validTabs) ? request('tab') : 'tong-quan';
        $navItems = [
            ['id' => 'tong-quan', 'icon' => 'dashboard', 'label' => 'Tổng quan'],
            ['id' => 'hom-nay', 'icon' => 'calendar', 'label' => 'Hôm nay'],
            ['id' => 'du-kien', 'icon' => 'calendar', 'label' => 'Dự kiến'],
            ['id' => 'hoan-thanh', 'icon' => 'check-circle', 'label' => 'Đã hoàn thành'],
        ];
    @endphp
    <div class="flex flex-col xl:flex-row gap-4 xl:gap-6">
        {{-- Sidebar Công việc --}}
        <nav class="xl:w-72 shrink-0 rounded-xl border border-gray-200 bg-white text-gray-900 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white px-4 py-5 xl:px-5 xl:py-6 min-h-[60vh]">
            <ul class="space-y-0.5">
                @foreach($navItems as $item)
                    @php
                        $href = $item['id'] === 'tong-quan' ? route('cong-viec') : route('cong-viec', ['tab' => $item['id']]);
                        $isActive = $currentTab === $item['id'];
                    @endphp
                    <li>
                        <a href="{{ $href }}"
                            class="menu-item {{ $isActive ? 'menu-item-active bg-brand-50 text-brand-500 dark:bg-brand-500/[0.12] dark:text-brand-400' : 'menu-item-inactive text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5' }}">
                            <span class="flex shrink-0 w-6 h-6 [&_svg]:w-6 [&_svg]:h-6 {!! $isActive ? 'menu-item-icon-active' : 'menu-item-icon-inactive' !!}">{!! \App\Helpers\MenuHelper::getIconSvg($item['icon']) !!}</span>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    </li>
                @endforeach
                <li class="pt-3 mt-3 border-t border-gray-200 dark:border-gray-700">
                    <a href="{{ route('cong-viec.programs.index') }}" class="menu-item menu-item-inactive text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5">
                        <span class="flex shrink-0 w-6 h-6 [&_svg]:w-6 [&_svg]:h-6">{!! \App\Helpers\MenuHelper::getIconSvg('calendar') !!}</span>
                        <span>Chương trình</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('cong-viec.behavior-baseline.edit') }}" class="menu-item menu-item-inactive text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5">
                        <span class="flex shrink-0 w-6 h-6 [&_svg]:w-6 [&_svg]:h-6">{!! \App\Helpers\MenuHelper::getIconSvg('settings') !!}</span>
                        <span>Thiết lập hành vi</span>
                    </a>
                </li>
            </ul>
        </nav>

        {{-- Nội dung --}}
        <div class="flex-1 min-w-0 flex flex-col rounded-xl border border-gray-200 bg-white text-gray-900 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white min-h-[60vh]">
            <div class="flex shrink-0 items-center justify-end gap-2 px-5 py-3 xl:px-10">
                <div class="relative" x-data="{ layoutOpen: false, currentLayout: (() => { try { return localStorage.getItem('congViecLayout') || 'list'; } catch(e) { return 'list'; } })() }" @cong-viec-layout.window="currentLayout = $event.detail.layout">
                    <button type="button" @click="layoutOpen = !layoutOpen; currentLayout = (localStorage.getItem('congViecLayout') || 'list')" @click.outside="layoutOpen = false"
                        class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
                        <span>Layout</span>
                        <svg class="h-4 w-4 transition-transform" :class="layoutOpen && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="layoutOpen" x-cloak x-transition
                        class="absolute right-0 top-full z-20 mt-1 w-40 rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                        <button type="button" @click="layoutOpen = false; $dispatch('cong-viec-layout', { layout: 'list' })"
                            class="layout-option flex w-full items-center gap-3 px-3 py-2.5 text-left text-sm transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50"
                            :class="currentLayout === 'list' ? 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-white' : 'text-gray-700 dark:text-gray-300'" data-layout="list">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg" :class="currentLayout === 'list' ? 'bg-white dark:bg-gray-600' : 'bg-gray-100 dark:bg-gray-700'">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" x2="21" y1="6" y2="6"/><line x1="8" x2="21" y1="12" y2="12"/><line x1="8" x2="21" y1="18" y2="18"/><line x1="3" x2="3.01" y1="6" y2="6"/><line x1="3" x2="3.01" y1="12" y2="12"/><line x1="3" x2="3.01" y1="18" y2="18"/></svg>
                            </span>
                            <span>List</span>
                        </button>
                        <button type="button" @click="layoutOpen = false; $dispatch('cong-viec-layout', { layout: 'board' })"
                            class="layout-option flex w-full items-center gap-3 px-3 py-2.5 text-left text-sm transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50"
                            :class="currentLayout === 'board' ? 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-white' : 'text-gray-700 dark:text-gray-300'" data-layout="board">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg" :class="currentLayout === 'board' ? 'bg-white dark:bg-gray-600' : 'bg-gray-100 dark:bg-gray-700'">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
                            </span>
                            <span>Board</span>
                        </button>
                        <button type="button" @click="layoutOpen = false; $dispatch('cong-viec-layout', { layout: 'calendar' })"
                            class="layout-option flex w-full items-center gap-3 px-3 py-2.5 text-left text-sm transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50"
                            :class="currentLayout === 'calendar' ? 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-white' : 'text-gray-700 dark:text-gray-300'" data-layout="calendar">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg" :class="currentLayout === 'calendar' ? 'bg-white dark:bg-gray-600' : 'bg-gray-100 dark:bg-gray-700'">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                            </span>
                            <span>Calendar</span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="flex-1 overflow-auto px-5 py-7 xl:px-10 xl:py-12">
                @yield('congViecContent')
            </div>
        </div>

        {{-- Cột phải: Program Context Panel --}}
        <div class="xl:w-72 shrink-0 rounded-xl border border-gray-200 bg-white text-gray-900 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white px-5 py-7 xl:px-6 xl:py-8 min-h-[60vh]">
            @yield('congViecRightColumn')
        </div>
    </div>
@endsection
