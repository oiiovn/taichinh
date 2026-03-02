@extends('layouts.app')

@section('contentWrapperClass')
    w-full p-4 md:p-6
@endsection

@section('content')
    @php
        $path = request()->path();
        $isSanPham = $path === 'food/san-pham';
        $isBaoCao = str_starts_with($path, 'food/bao-cao-ban-hang');
        $isCongNo = $path === 'food/cong-no';
        $validTabs = ['tong-quan', 'danh-sach'];
        $currentTab = $isCongNo ? 'cong-no' : ($isBaoCao ? 'bao-cao-ban-hang' : ($isSanPham ? 'san-pham' : (in_array(request('tab'), $validTabs) ? request('tab') : 'tong-quan')));
        $navItems = [
            ['id' => 'tong-quan', 'icon' => 'dashboard', 'label' => 'Tổng quan', 'path' => route('food')],
            ['id' => 'danh-sach', 'icon' => 'list', 'label' => 'Danh sách', 'path' => route('food', ['tab' => 'danh-sach'])],
            ['id' => 'san-pham', 'icon' => 'ecommerce', 'label' => 'Sản phẩm', 'path' => route('food.san-pham')],
            ['id' => 'bao-cao-ban-hang', 'icon' => 'chart-bar', 'label' => 'Báo cáo bán hàng', 'path' => route('food.bao-cao-ban-hang')],
            ['id' => 'cong-no', 'icon' => 'chart-bar', 'label' => 'Công nợ', 'path' => route('food.cong-no')],
        ];
        if (! auth()->user()?->is_admin) {
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
        <div class="flex-1 min-w-0 flex flex-col rounded-xl border border-gray-200 bg-white text-gray-900 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white min-h-[60vh]">
            <div class="flex-1 overflow-auto px-5 py-7 xl:px-10 xl:py-12">
                @yield('foodContent')
            </div>
        </div>
    </div>
@endsection
