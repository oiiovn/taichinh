@extends('layouts.app')

@section('contentWrapperClass')
    w-full p-4 md:p-6
@endsection

@section('content')
    @php
        $path = request()->path();
        $isSanPham = ($path === 'food/san-pham');
        $isChiNhanh = ($path === 'food/chi-nhanh');
        $isThongKeBuff = ($path === 'food/thong-ke-buff');
        $isBaoCao = str_starts_with($path, 'food/bao-cao-ban-hang');
        $isKhachHang = ($path === 'food/khach-hang');
        $isCongNo = ($path === 'food/cong-no');
        $isNhanVien = str_starts_with($path, 'food/nhan-vien');
        $isChamCong = str_starts_with($path, 'food/cham-cong');
        $isXinNghi = str_starts_with($path, 'food/xin-nghi');
        $isUngLuong = str_starts_with($path, 'food/ung-luong');
        $isLuong = str_starts_with($path, 'food/luong') && $path !== 'food/luong-cua-toi';
        $isLuongCuaToi = ($path === 'food/luong-cua-toi');
        $isDoanhSo = ($path === 'food' && request('tab') === 'doanh-so');
        $isQrChamCong = ($path === 'food/qr-cham-cong');
        $validTabs = ['tong-quan', 'doanh-so'];
        if ($isQrChamCong) {
            $currentTab = 'qr-cham-cong';
        } elseif ($isChiNhanh) {
            $currentTab = 'chi-nhanh';
        } elseif ($isThongKeBuff) {
            $currentTab = 'thong-ke-buff';
        } elseif ($isLuongCuaToi) {
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
        } elseif ($isDoanhSo) {
            $currentTab = 'doanh-so';
        } elseif ($isKhachHang) {
            $currentTab = 'khach-hang';
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
        $canManageAnyFood = $user && method_exists($user, 'canManageAnyFood') ? $user->canManageAnyFood() : false;
        $canManageNhanVien = $user && method_exists($user, 'canManageFoodEmployees') ? $user->canManageFoodEmployees() : false;
        $canManageChamCong = $user && method_exists($user, 'canManageFoodChamCong') ? $user->canManageFoodChamCong() : false;
        $canManageXinNghi = $user && method_exists($user, 'canManageFoodXinNghi') ? $user->canManageFoodXinNghi() : false;
        $canManageUngLuong = $user && method_exists($user, 'canManageFoodUngLuong') ? $user->canManageFoodUngLuong() : false;
        $canManageLuong = $user && method_exists($user, 'canManageFoodLuong') ? $user->canManageFoodLuong() : false;
        $canManageTongQuan = $user && method_exists($user, 'canManageFoodTongQuan') ? $user->canManageFoodTongQuan() : false;
        $canManageDoanhSo = $user && method_exists($user, 'canManageFoodDoanhSo') ? $user->canManageFoodDoanhSo() : false;
        $canManageSanPham = $user && method_exists($user, 'canManageFoodSanPham') ? $user->canManageFoodSanPham() : false;
        $canManageBaoCao = $user && method_exists($user, 'canManageFoodBaoCao') ? $user->canManageFoodBaoCao() : false;
        $isEmployee = $user && $hasFoodEmployees && $user->employee && method_exists($user, 'canUseFoodEmployee') && $user->canUseFoodEmployee();
        $canUseQrChamCong = $user && method_exists($user, 'canUseQrChamCong') && $user->canUseQrChamCong();
        $isOnlyEmployee = $isEmployee && !$canManageAnyFood;
        $navItems = [
            ['id' => 'tong-quan', 'icon' => 'dashboard', 'label' => 'Tổng quan', 'path' => route('food'), 'show' => $canManageTongQuan],
            ['id' => 'doanh-so', 'icon' => 'chart-bar', 'label' => 'Doanh số', 'path' => route('food', ['tab' => 'doanh-so']), 'show' => $canManageDoanhSo],
            ['id' => 'san-pham', 'icon' => 'ecommerce', 'label' => 'Sản phẩm', 'path' => route('food.san-pham'), 'show' => $canManageSanPham],
            ['id' => 'chi-nhanh', 'icon' => 'tables', 'label' => 'Chi nhánh', 'path' => route('food.chi-nhanh'), 'show' => $canManageBaoCao && \Illuminate\Support\Facades\Route::has('food.chi-nhanh')],
            ['id' => 'bao-cao-ban-hang', 'icon' => 'chart-bar', 'label' => 'Báo cáo bán hàng', 'path' => route('food.bao-cao-ban-hang'), 'show' => $canManageBaoCao],
            ['id' => 'thong-ke-buff', 'icon' => 'charts', 'label' => 'Thống kê Buff', 'path' => route('food.thong-ke-buff'), 'show' => $canManageBaoCao && \Illuminate\Support\Facades\Route::has('food.thong-ke-buff')],
            ['id' => 'khach-hang', 'icon' => 'users', 'label' => 'Khách hàng', 'path' => route('food.khach-hang'), 'show' => $canManageBaoCao],
            ['id' => 'cong-no', 'icon' => 'chart-bar', 'label' => 'Công nợ', 'path' => route('food.cong-no'), 'show' => !$isEmployee],
        ];
        if ($hasFoodEmployees) {
            if (\Illuminate\Support\Facades\Route::has('food.nhan-vien')) {
                $navItems[] = ['id' => 'nhan-vien', 'icon' => 'users', 'label' => 'Nhân viên', 'path' => route('food.nhan-vien'), 'show' => $canManageNhanVien];
            }
            if (\Illuminate\Support\Facades\Route::has('food.cham-cong')) {
                $navItems[] = ['id' => 'cham-cong', 'icon' => 'check-circle', 'label' => 'Chấm công', 'path' => route('food.cham-cong'), 'show' => $canManageChamCong || $isOnlyEmployee];
            }
            if (\Illuminate\Support\Facades\Route::has('food.xin-nghi')) {
                $navItems[] = ['id' => 'xin-nghi', 'icon' => 'calendar', 'label' => 'Xin nghỉ', 'path' => route('food.xin-nghi'), 'show' => $canManageXinNghi || $isOnlyEmployee];
            }
            if (\Illuminate\Support\Facades\Route::has('food.ung-luong')) {
                $navItems[] = ['id' => 'ung-luong', 'icon' => 'card', 'label' => 'Ứng lương', 'path' => route('food.ung-luong'), 'show' => $canManageUngLuong || $isOnlyEmployee];
            }
            if (\Illuminate\Support\Facades\Route::has('food.luong')) {
                $navItems[] = ['id' => 'luong', 'icon' => 'chart-bar', 'label' => 'Bảng lương', 'path' => route('food.luong'), 'show' => $canManageLuong];
            }
            if (\Illuminate\Support\Facades\Route::has('food.luong-cua-toi')) {
                $navItems[] = ['id' => 'luong-cua-toi', 'icon' => 'chart-bar', 'label' => 'Lương của tôi', 'path' => route('food.luong-cua-toi'), 'show' => $isOnlyEmployee];
            }
        }
        if ($canUseQrChamCong && \Illuminate\Support\Facades\Route::has('food.qr-cham-cong')) {
            $navItems[] = ['id' => 'qr-cham-cong', 'icon' => 'check-circle', 'label' => 'QR chấm công', 'path' => route('food.qr-cham-cong'), 'show' => true];
        }
        $navItems = array_values(array_filter($navItems, fn ($item) => $item['show'] ?? true));
        if (!$canManageAnyFood && !$isEmployee) {
            if ($canUseQrChamCong) {
                $navItems = array_values(array_filter($navItems, fn ($item) => $item['id'] === 'qr-cham-cong'));
            } else {
                $navItems = array_values(array_filter($navItems, fn ($item) => $item['id'] === 'cong-no'));
            }
        }
    @endphp
    <div class="flex flex-col xl:flex-row gap-4 xl:gap-6" x-data="{ menuOpen: false }">
        {{-- Cột menu con --}}
        <div class="xl:w-72 shrink-0 relative">
            {{-- Mobile: nút 3 gạch, nhấn vào xổ menu --}}
            <div class="xl:hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <button type="button" @click="menuOpen = !menuOpen" class="flex w-full items-center gap-3 rounded-xl px-4 py-3.5 text-left text-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                    <span class="flex shrink-0 text-gray-500 dark:text-gray-400" aria-hidden="true">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </span>
                    <span class="text-sm font-medium">Menu</span>
                </button>
            </div>
            {{-- Mobile: menu xổ ra (Alpine x-show) --}}
            <div class="xl:hidden absolute left-0 right-0 top-full z-50 mt-1"
                x-show="menuOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-2"
                @click.outside="menuOpen = false"
                style="display: none;">
                <nav class="rounded-xl border border-gray-200 bg-white shadow-lg dark:border-gray-800 dark:bg-gray-900 px-4 py-3 text-gray-900 dark:text-white">
                    <ul class="space-y-0.5">
                        @foreach($navItems as $item)
                            @php $isActive = $currentTab === $item['id']; @endphp
                            <li>
                                <a href="{{ $item['path'] }}"
                                    @click="menuOpen = false"
                                    class="menu-item flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors {{ $isActive ? 'menu-item-active bg-brand-50 text-brand-500 dark:bg-brand-500/[0.12] dark:text-brand-400' : 'menu-item-inactive text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5' }}">
                                    <span class="flex shrink-0 w-6 h-6 [&_svg]:w-6 [&_svg]:h-6">{!! \App\Helpers\MenuHelper::getIconSvg($item['icon']) !!}</span>
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            </div>
            {{-- Desktop: menu luôn hiện --}}
            <nav class="hidden xl:block rounded-xl border border-gray-200 bg-white text-gray-900 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white px-4 py-5 xl:px-5 xl:py-6 min-h-[60vh] xl:min-h-0">
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
        </div>

        {{-- Nội dung (không cột phải) --}}
        <div class="flex-1 min-w-0 flex flex-col rounded-xl border border-gray-200 bg-white text-gray-900 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white min-h-[60vh] overflow-hidden">
            <div class="flex-1 overflow-auto overflow-x-hidden px-5 pr-8 py-7 xl:pl-10 xl:pr-10 xl:py-12">
                @yield('foodContent')
            </div>
        </div>
    </div>
@endsection
