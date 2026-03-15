@extends('layouts.app')

@section('contentWrapperClass')
    w-full p-4 md:p-6
@endsection

@section('content')
    @php
        $path = request()->path();
        $isSanPham = ($path === 'food/san-pham');
        $isBaoCao = str_starts_with($path, 'food/bao-cao-ban-hang');
        $isCongNo = ($path === 'food/cong-no');
        $isNhanVien = str_starts_with($path, 'food/nhan-vien');
        $isChamCong = str_starts_with($path, 'food/cham-cong');
        $isXinNghi = str_starts_with($path, 'food/xin-nghi');
        $isUngLuong = str_starts_with($path, 'food/ung-luong');
        $isLuong = str_starts_with($path, 'food/luong') && $path !== 'food/luong-cua-toi';
        $isLuongCuaToi = ($path === 'food/luong-cua-toi');
        $validTabs = ['tong-quan', 'danh-sach'];
        if ($isLuongCuaToi) {
            $currentTab = 'luong-cua-toi';
        } elseif ($isLuong) {
            $currentTab = 'luong';
        } elseif ($isUngLuong) {
            $currentTab = 'ung-luong';
        } elseif ($isXinNghi) {
            $currentTab = 'xin-nghi';
        } elseif ($isChamCong) {
            $currentTab = 'cham-cong';
        } elseif ($isNhanVien) {
            $currentTab = 'nhan-vien';
        } elseif ($isCongNo) {
            $currentTab = 'cong-no';
        } elseif ($isBaoCao) {
            $currentTab = 'bao-cao-ban-hang';
        } elseif ($isSanPham) {
            $currentTab = 'san-pham';
        } elseif (in_array(request('tab'), $validTabs)) {
            $currentTab = request('tab');
        } else {
            $currentTab = 'tong-quan';
        }
        $user = auth()->user();
        $hasFoodEmployees = class_exists(\App\Models\Employee::class);
        $canManage = $user && method_exists($user, 'canManageFoodEmployees') ? $user->canManageFoodEmployees() : false;
        $isEmployee = $user && $hasFoodEmployees && $user->employee;
        $navItems = [
            ['id' => 'tong-quan', 'icon' => 'dashboard', 'label' => 'Tổng quan', 'path' => route('food'), 'show' => $canManage],
            ['id' => 'danh-sach', 'icon' => 'list', 'label' => 'Danh sách', 'path' => route('food', ['tab' => 'danh-sach']), 'show' => $canManage],
            ['id' => 'san-pham', 'icon' => 'ecommerce', 'label' => 'Sản phẩm', 'path' => route('food.san-pham'), 'show' => $canManage],
            ['id' => 'bao-cao-ban-hang', 'icon' => 'chart-bar', 'label' => 'Báo cáo bán hàng', 'path' => route('food.bao-cao-ban-hang'), 'show' => $canManage],
            ['id' => 'cong-no', 'icon' => 'chart-bar', 'label' => 'Công nợ', 'path' => route('food.cong-no'), 'show' => !$isEmployee],
        ];
        if ($hasFoodEmployees) {
            if (\Illuminate\Support\Facades\Route::has('food.nhan-vien')) {
                $navItems[] = ['id' => 'nhan-vien', 'icon' => 'users', 'label' => 'Nhân viên', 'path' => route('food.nhan-vien'), 'show' => $canManage];
            }
            if (\Illuminate\Support\Facades\Route::has('food.cham-cong')) {
                $navItems[] = ['id' => 'cham-cong', 'icon' => 'check-circle', 'label' => 'Chấm công', 'path' => route('food.cham-cong'), 'show' => $canManage || $isEmployee];
            }
            if (\Illuminate\Support\Facades\Route::has('food.xin-nghi')) {
                $navItems[] = ['id' => 'xin-nghi', 'icon' => 'calendar', 'label' => 'Xin nghỉ', 'path' => route('food.xin-nghi'), 'show' => $canManage || $isEmployee];
            }
            if (\Illuminate\Support\Facades\Route::has('food.ung-luong')) {
                $navItems[] = ['id' => 'ung-luong', 'icon' => 'card', 'label' => 'Ứng lương', 'path' => route('food.ung-luong'), 'show' => $canManage || $isEmployee];
            }
            if (\Illuminate\Support\Facades\Route::has('food.luong')) {
                $navItems[] = ['id' => 'luong', 'icon' => 'chart-bar', 'label' => 'Bảng lương', 'path' => route('food.luong'), 'show' => $canManage];
            }
            if (\Illuminate\Support\Facades\Route::has('food.luong-cua-toi')) {
                $navItems[] = ['id' => 'luong-cua-toi', 'icon' => 'chart-bar', 'label' => 'Lương của tôi', 'path' => route('food.luong-cua-toi'), 'show' => $isEmployee];
            }
        }
        $navItems = array_values(array_filter($navItems, fn ($item) => $item['show'] ?? true));
        if (!$canManage && !$isEmployee) {
            $navItems = array_values(array_filter($navItems, fn ($item) => $item['id'] === 'cong-no'));
        }
    @endphp
    <div class="flex flex-col xl:flex-row gap-4 xl:gap-6">
        {{-- Cột menu con --}}
        <nav class="xl:w-72 shrink-0 rounded-xl border border-gray-200 bg-white text-gray-900 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white px-4 py-5 xl:px-5 xl:py-6 min-h-[60vh]">
            <ul class="space-y-0.5">
                @foreach($navItems as $item)
                    @php $isActive = $currentTab === $item['id']; @endphp
                    <li>
                        <a href="{{ $item['path'] }}"
                            class="menu-item flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors {{ $isActive ? 'menu-item-active bg-brand-50 text-brand-500 dark:bg-brand-500/[0.12] dark:text-brand-400' : 'menu-item-inactive text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5' }}">
                            <span class="flex shrink-0 w-6 h-6 [&_svg]:w-6 [&_svg]:h-6">{!! \App\Helpers\MenuHelper::getIconSvg($item['icon']) !!}</span>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        {{-- Nội dung (không cột phải) --}}
        <div class="flex-1 min-w-0 flex flex-col rounded-xl border border-gray-200 bg-white text-gray-900 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white min-h-[60vh] overflow-hidden">
            <div class="flex-1 overflow-auto overflow-x-hidden px-5 pr-8 py-7 xl:pl-10 xl:pr-10 xl:py-12">
                @yield('foodContent')
            </div>
        </div>
    </div>
@endsection
