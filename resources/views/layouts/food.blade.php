@extends('layouts.app')

@section('contentWrapperClass')
    w-full p-0 sm:p-4 md:p-5
@endsection

@section('content')
    @php
        $path = request()->path();
        $isSanPham = ($path === 'food/san-pham');
        $isChiNhanh = ($path === 'food/chi-nhanh');
        $isThongKeBuff = ($path === 'food/thong-ke-buff');
        $isLichDatDon = ($path === 'food/lich-dat-don');
        $isLichDaXacNhan = ($path === 'food/lich-da-xac-nhan');
        $isDatDonFood = str_starts_with($path, 'food/dat-don');
        $isFoodReviews = str_starts_with($path, 'food/danh-gia');
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
        } elseif ($isFoodReviews) {
            $currentTab = 'food-reviews';
        } elseif ($isChiNhanh) {
            $currentTab = 'chi-nhanh';
        } elseif ($isThongKeBuff) {
            $currentTab = 'thong-ke-buff';
        } elseif ($isLichDatDon) {
            $currentTab = 'lich-dat-don';
        } elseif ($isLichDaXacNhan) {
            $currentTab = 'lich-da-xac-nhan';
        } elseif ($isDatDonFood) {
            $currentTab = 'dat-don';
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
        $canViewFoodPayroll = $user && method_exists($user, 'canViewFoodPayroll') ? $user->canViewFoodPayroll() : false;
        $canRecordFoodSalaryPayment = $user && method_exists($user, 'canRecordFoodSalaryPayment') ? $user->canRecordFoodSalaryPayment() : false;
        $canManageTongQuan = $user && method_exists($user, 'canManageFoodTongQuan') ? $user->canManageFoodTongQuan() : false;
        $canManageDoanhSo = $user && method_exists($user, 'canManageFoodDoanhSo') ? $user->canManageFoodDoanhSo() : false;
        $canManageSanPham = $user && method_exists($user, 'canManageFoodSanPham') ? $user->canManageFoodSanPham() : false;
        $canManageBaoCao = $user && method_exists($user, 'canManageFoodBaoCao') ? $user->canManageFoodBaoCao() : false;
        $canManageThongKeBuff = $user && method_exists($user, 'canManageFoodThongKeBuff') ? $user->canManageFoodThongKeBuff() : false;
        $canCreateFoodBuffOrder = $user && method_exists($user, 'canCreateFoodBuffOrder') ? $user->canCreateFoodBuffOrder() : false;
        $canManageFoodReviews = $user && method_exists($user, 'canManageFoodReviews') ? $user->canManageFoodReviews() : false;
        $canViewCongNo = $user && ($user->is_admin || $canManageBaoCao);
        $isEmployee = $user && $hasFoodEmployees && $user->employee && method_exists($user, 'canUseFoodEmployee') && $user->canUseFoodEmployee();
        $canUseQrChamCong = $user && method_exists($user, 'canUseQrChamCong') && $user->canUseQrChamCong();
        $isOnlyEmployee = $isEmployee && !$canManageAnyFood;
        $isOnlyThongKeBuff = $user
            && !$user->is_admin
            && $canManageThongKeBuff
            && !$canCreateFoodBuffOrder
            && !$canManageTongQuan
            && !$canManageDoanhSo
            && !$canManageSanPham
            && !$canManageBaoCao
            && !$canManageFoodReviews
            && !$canManageNhanVien
            && !$canManageChamCong
            && !$canManageXinNghi
            && !$canManageUngLuong
            && !$canManageLuong
            && !$canUseQrChamCong;
        $navItems = [
            ['id' => 'tong-quan', 'icon' => 'dashboard', 'label' => 'Tổng quan', 'path' => route('food'), 'show' => $canManageTongQuan],
            ['id' => 'doanh-so', 'icon' => 'chart-bar', 'label' => 'Doanh số', 'path' => route('food', ['tab' => 'doanh-so']), 'show' => $canManageDoanhSo],
            ['id' => 'san-pham', 'icon' => 'ecommerce', 'label' => 'Sản phẩm', 'path' => route('food.san-pham'), 'show' => $canManageSanPham],
            ['id' => 'chi-nhanh', 'icon' => 'tables', 'label' => 'Chi nhánh', 'path' => route('food.chi-nhanh'), 'show' => $canManageBaoCao && \Illuminate\Support\Facades\Route::has('food.chi-nhanh')],
            ['id' => 'bao-cao-ban-hang', 'icon' => 'chart-bar', 'label' => 'Báo cáo bán hàng', 'path' => route('food.bao-cao-ban-hang'), 'show' => $canManageBaoCao],
            ['id' => 'thong-ke-buff', 'icon' => 'charts', 'label' => 'Thống kê seeding', 'path' => route('food.thong-ke-buff'), 'show' => $canManageThongKeBuff && \Illuminate\Support\Facades\Route::has('food.thong-ke-buff')],
            ['id' => 'lich-dat-don', 'icon' => 'calendar', 'label' => 'Lịch đặt đơn', 'path' => route('food.lich-dat-don'), 'show' => $user && $user->is_admin && $canManageThongKeBuff && \Illuminate\Support\Facades\Route::has('food.lich-dat-don')],
            ['id' => 'dat-don', 'icon' => 'ecommerce', 'label' => 'Đặt đơn ShopeeFood', 'path' => route('food.dat-don'), 'show' => $canCreateFoodBuffOrder && \Illuminate\Support\Facades\Route::has('food.dat-don')],
            ['id' => 'lich-da-xac-nhan', 'icon' => 'calendar', 'label' => 'Lịch đã xác nhận', 'path' => route('food.lich-da-xac-nhan'), 'show' => ($canCreateFoodBuffOrder || $canManageThongKeBuff) && \Illuminate\Support\Facades\Route::has('food.lich-da-xac-nhan')],
            ['id' => 'food-reviews', 'icon' => 'charts', 'label' => 'Đánh giá', 'path' => route('food.reviews.index'), 'show' => $canManageFoodReviews && \Illuminate\Support\Facades\Route::has('food.reviews.index')],
            ['id' => 'food-reviews-qr', 'icon' => 'check-circle', 'label' => 'QR nhận quà 5 sao', 'path' => route('food.qr-public-review-gift'), 'show' => $canManageFoodReviews && \Illuminate\Support\Facades\Route::has('food.qr-public-review-gift')],
            ['id' => 'khach-hang', 'icon' => 'users', 'label' => 'Khách hàng', 'path' => route('food.khach-hang'), 'show' => $canManageBaoCao],
            ['id' => 'cong-no', 'icon' => 'chart-bar', 'label' => 'Công nợ', 'path' => route('food.cong-no'), 'show' => !$isEmployee && $canViewCongNo],
        ];
        if ($hasFoodEmployees) {
            if (\Illuminate\Support\Facades\Route::has('food.nhan-vien')) {
                $navItems[] = ['id' => 'nhan-vien', 'icon' => 'users', 'label' => 'Nhân viên', 'path' => route('food.nhan-vien'), 'show' => $canManageNhanVien];
            }
            if (\Illuminate\Support\Facades\Route::has('food.cham-cong')) {
                $navItems[] = ['id' => 'cham-cong', 'icon' => 'check-circle', 'label' => 'Chấm công', 'path' => route('food.cham-cong'), 'show' => $canManageChamCong || $isEmployee];
            }
            if (\Illuminate\Support\Facades\Route::has('food.xin-nghi')) {
                $navItems[] = ['id' => 'xin-nghi', 'icon' => 'calendar', 'label' => 'Xin nghỉ', 'path' => route('food.xin-nghi'), 'show' => $canManageXinNghi || $isEmployee];
            }
            if (\Illuminate\Support\Facades\Route::has('food.ung-luong')) {
                $navItems[] = ['id' => 'ung-luong', 'icon' => 'card', 'label' => 'Ứng lương', 'path' => route('food.ung-luong'), 'show' => $canManageUngLuong || $isEmployee];
            }
            if (\Illuminate\Support\Facades\Route::has('food.luong')) {
                $navItems[] = ['id' => 'luong', 'icon' => 'chart-bar', 'label' => 'Bảng lương', 'path' => route('food.luong'), 'show' => $canViewFoodPayroll];
            }
            if (\Illuminate\Support\Facades\Route::has('food.luong-cua-toi')) {
                $navItems[] = ['id' => 'luong-cua-toi', 'icon' => 'chart-bar', 'label' => 'Lương của tôi', 'path' => route('food.luong-cua-toi'), 'show' => $isEmployee && ! $canManageLuong];
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
        if ($isOnlyThongKeBuff) {
            $onlyThongKeNavIds = ['thong-ke-buff'];
            if ($isEmployee) {
                $onlyThongKeNavIds = array_merge($onlyThongKeNavIds, ['cham-cong', 'xin-nghi', 'ung-luong', 'luong-cua-toi']);
            }
            if ($canCreateFoodBuffOrder) {
                $onlyThongKeNavIds = array_merge($onlyThongKeNavIds, ['dat-don', 'lich-da-xac-nhan']);
            }
            $navItems = array_values(array_filter($navItems, fn ($item) => in_array($item['id'], $onlyThongKeNavIds, true)));
        }
        $navItemsById = collect($navItems)->keyBy('id');
        $menuGroupDefs = [
            ['key' => 'tong-quan', 'label' => 'Tổng quan', 'ids' => ['tong-quan', 'doanh-so', 'bao-cao-ban-hang', 'cong-no']],
            ['key' => 'don-hang', 'label' => 'Đơn hàng & Seeding', 'ids' => ['dat-don', 'lich-da-xac-nhan', 'lich-dat-don', 'thong-ke-buff', 'food-reviews', 'food-reviews-qr']],
            ['key' => 'danh-muc', 'label' => 'Danh mục', 'ids' => ['san-pham', 'chi-nhanh', 'khach-hang']],
            ['key' => 'nhan-su', 'label' => 'Nhân sự', 'ids' => ['nhan-vien', 'cham-cong', 'xin-nghi', 'ung-luong', 'luong', 'luong-cua-toi', 'qr-cham-cong']],
        ];
        $navGroups = [];
        $groupedIds = [];
        foreach ($menuGroupDefs as $groupDef) {
            $items = [];
            foreach ($groupDef['ids'] as $id) {
                $groupedIds[] = $id;
                if ($navItemsById->has($id)) {
                    $items[] = $navItemsById->get($id);
                }
            }
            if ($items !== []) {
                $navGroups[] = [
                    'key' => $groupDef['key'],
                    'label' => $groupDef['label'],
                    'items' => $items,
                    'open' => collect($items)->contains(fn ($item) => $item['id'] === $currentTab),
                ];
            }
        }
        $ungrouped = array_values(array_filter($navItems, fn ($item) => ! in_array($item['id'], $groupedIds, true)));
        if ($ungrouped !== []) {
            $navGroups[] = [
                'key' => 'other',
                'label' => null,
                'items' => $ungrouped,
                'open' => collect($ungrouped)->contains(fn ($item) => $item['id'] === $currentTab),
            ];
        }
        if ($navGroups !== [] && ! collect($navGroups)->contains(fn ($g) => $g['open'])) {
            $navGroups[0]['open'] = true;
        }
        $showFoodMenu = count($navItems) > 1;
        $currentNavLabel = collect($navItems)->firstWhere('id', $currentTab)['label']
            ?? collect($navGroups)->flatMap(fn ($g) => $g['items'])->firstWhere('id', $currentTab)['label']
            ?? 'Food';
    @endphp
    <div class="flex flex-col xl:flex-row gap-0 xl:gap-6"
        x-data="{ menuOpen: false }"
        @keydown.escape.window="menuOpen = false">

        {{-- Mobile app top bar --}}
        @if($showFoodMenu)
        <div class="xl:hidden sticky top-0 z-40 border-b border-gray-200/80 bg-white/90 px-4 py-2.5 backdrop-blur-xl dark:border-gray-800 dark:bg-gray-950/90">
            <div class="flex items-center gap-2.5">
                <button type="button"
                    @click="menuOpen = true"
                    class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gray-100 text-gray-800 transition active:scale-95 dark:bg-gray-800 dark:text-gray-100"
                    aria-label="Mở menu Food">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-brand-600 dark:text-brand-400">Food</p>
                    <h1 class="truncate text-base font-semibold tracking-tight text-gray-950 dark:text-white">{{ $currentNavLabel }}</h1>
                </div>
            </div>
        </div>

        {{-- Mobile full-screen drawer --}}
        <div class="xl:hidden fixed inset-0 z-[60]"
            x-show="menuOpen"
            x-cloak
            style="display: none;">
            <div class="absolute inset-0 bg-black/45 backdrop-blur-[2px]"
                x-show="menuOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="menuOpen = false"></div>
            <div class="absolute inset-y-0 left-0 flex w-[86%] max-w-sm flex-col bg-white shadow-2xl dark:bg-gray-950"
                x-show="menuOpen"
                x-transition:enter="transition ease-out duration-250"
                x-transition:enter-start="-translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="-translate-x-full"
                @click.stop>
                <div class="flex items-center justify-between border-b border-gray-100 px-4 pb-3 pt-[max(0.75rem,env(safe-area-inset-top))] dark:border-gray-800">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-brand-600 dark:text-brand-400">Food</p>
                        <p class="text-lg font-semibold text-gray-950 dark:text-white">Menu</p>
                    </div>
                    <button type="button"
                        @click="menuOpen = false"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-600 transition active:scale-95 dark:bg-gray-800 dark:text-gray-300"
                        aria-label="Đóng menu">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto overscroll-contain px-3 py-3 pb-[max(1rem,env(safe-area-inset-bottom))]">
                    @include('pages.food.partials.food-menu-list', ['closeOnClick' => true, 'appStyle' => true])
                </div>
            </div>
        </div>

        {{-- Desktop sidebar --}}
        <div class="hidden xl:block xl:w-72 shrink-0">
            <nav class="rounded-xl border border-gray-200 bg-white text-gray-900 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white px-4 py-5 xl:px-5 xl:py-6 min-h-[60vh] max-h-[calc(100vh-6rem)] overflow-y-auto">
                @include('pages.food.partials.food-menu-list')
            </nav>
        </div>
        @endif

        {{-- Nội dung --}}
        <div class="flex-1 min-w-0 min-h-[60vh] overflow-x-hidden px-4 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-3 sm:px-0 sm:pb-0 xl:pt-0 text-gray-900 dark:text-white">
            @yield('foodContent')
        </div>
    </div>
@endsection
