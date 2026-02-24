@extends('layouts.app')

@section('contentWrapperClass')
    w-full p-4 md:p-6
@endsection

@section('content')
    @php
        $validTabs = ['dashboard', 'tai-khoan', 'giao-dich', 'phan-tich', 'chien-luoc', 'nguong-ngan-sach', 'lich-thanh-toan', 'no-khoan-vay'];
        $activeTab = request()->routeIs('tai-chinh.loans.*') ? 'no-khoan-vay' : (in_array(request('tab'), $validTabs) ? request('tab') : 'dashboard');
        $navItems = [
            ['id' => 'dashboard', 'icon' => 'dashboard', 'label' => 'Dashboard'],
            ['id' => 'tai-khoan', 'icon' => 'card', 'label' => 'Tài khoản'],
            ['id' => 'giao-dich', 'icon' => 'chart-bar', 'label' => 'Giao dịch'],
            ['id' => 'phan-tich', 'icon' => 'chart-trend', 'label' => 'Phân tích'],
            ['id' => 'chien-luoc', 'icon' => 'target', 'label' => 'Chiến lược'],
            ['id' => 'nguong-ngan-sach', 'icon' => 'chart-bar', 'label' => 'Ngưỡng ngân sách'],
            ['id' => 'lich-thanh-toan', 'icon' => 'calendar', 'label' => 'Lịch thanh toán'],
            ['id' => 'no-khoan-vay', 'icon' => 'finance', 'label' => 'Nợ & Khoản vay'],
        ];
    @endphp
    <div class="flex flex-col xl:flex-row gap-4 xl:gap-6">
        {{-- Sidebar Tài chính (TailAdmin) --}}
        <nav class="xl:w-72 shrink-0 rounded-xl border border-gray-200 bg-white text-gray-900 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white px-4 py-5 xl:px-5 xl:py-6 min-h-[60vh]">
            <ul class="space-y-0.5">
                @foreach($navItems as $item)
                    @php
                        $href = $item['id'] === 'no-khoan-vay' ? route('tai-chinh', ['tab' => 'no-khoan-vay']) : route('tai-chinh', ['tab' => $item['id']]);
                        $isActive = $activeTab === $item['id'];
                    @endphp
                    <li>
                        <a href="{{ $href }}"
                            class="menu-item {{ $isActive ? 'menu-item-active bg-brand-50 text-brand-500 dark:bg-brand-500/[0.12] dark:text-brand-400' : 'menu-item-inactive text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5' }}">
                            <span class="flex shrink-0 w-6 h-6 [&_svg]:w-6 [&_svg]:h-6 {!! $isActive ? 'menu-item-icon-active' : 'menu-item-icon-inactive' !!}">{!! \App\Helpers\MenuHelper::getIconSvg($item['icon']) !!}</span>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        {{-- Nội dung --}}
        <div class="flex-1 min-w-0 rounded-xl border border-gray-200 bg-white text-gray-900 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white px-5 py-7 xl:px-10 xl:py-12 min-h-[60vh]">
            @if(!empty($planExpiringSoon) && $planExpiresAt && !empty($currentPlan))
                <a href="{{ route('goi-hien-tai.thanh-toan', ['plan' => $currentPlan]) }}" class="mb-5 flex w-full items-center gap-4 rounded-xl border-2 border-amber-500 bg-gradient-to-r from-amber-50 to-orange-50 px-4 py-3 shadow-sm transition hover:border-amber-600 hover:from-amber-100 hover:to-orange-100 dark:border-amber-500 dark:from-amber-500/20 dark:to-orange-500/20 dark:hover:from-amber-500/30 dark:hover:to-orange-500/30">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-500 text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-amber-900 dark:text-amber-100">Gói {{ strtoupper($currentPlan) }} sắp hết hạn {{ $planExpiresAt->format('d/m/Y') }}</p>
                        <p class="text-sm text-amber-700 dark:text-amber-200/90">Nhấn để gia hạn và tiếp tục sử dụng đầy đủ tính năng.</p>
                    </div>
                    <span class="shrink-0 font-medium text-amber-700 dark:text-amber-200">Gia hạn →</span>
                </a>
            @endif
            @yield('taiChinhContent')
        </div>

        {{-- Cột phải — timeline hành trình (chỉ tab Chiến lược) --}}
        <div class="xl:w-72 shrink-0 rounded-xl border border-gray-200 bg-white text-gray-900 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white px-5 py-7 xl:px-6 xl:py-8 min-h-[60vh]">
            @yield('taiChinhRightColumn')
        </div>
    </div>
    @if((request('tab') === 'no-khoan-vay') || request()->routeIs('tai-chinh.loans.index'))
    <script>(function(){if(window.self!==window.top){try{window.parent.postMessage({type:'no-khoan-vay-done'},'*');}catch(e){}}})();</script>
    @endif

    {{-- Popup chặn thao tác khi gói hết hạn, chỉ áp dụng tab Giao dịch --}}
    @php
        $planExpired = isset($planExpiresAt) && $planExpiresAt && !$planExpiresAt->isFuture();
        $showExpiredModal = $activeTab === 'giao-dich' && $planExpired;
    @endphp
    @if($showExpiredModal)
    <div class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" aria-labelledby="expired-modal-title">
        <div class="w-full max-w-md rounded-2xl border border-red-200 bg-white p-6 shadow-xl dark:border-red-800 dark:bg-gray-900 dark:text-white">
            <div class="flex items-center gap-3 text-red-600 dark:text-red-400">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/40">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <h2 id="expired-modal-title" class="text-xl font-semibold">Gói đã hết hạn</h2>
            </div>
            <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                Bạn không thể xem hoặc thao tác với giao dịch khi gói đã hết hạn. Gia hạn gói để tiếp tục sử dụng tính năng Giao dịch.
            </p>
            <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-end">
                <a href="{{ route('goi-hien-tai') }}" class="inline-flex justify-center rounded-lg bg-success-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-success-600 dark:bg-success-600 dark:hover:bg-success-500">Gia hạn gói</a>
            </div>
        </div>
    </div>
    @endif
@endsection
