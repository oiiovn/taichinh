<?php

namespace App\Services\Food;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Menu Food theo quyền user — dùng chung cho API mobile.
 * Logic khớp resources/views/layouts/food.blade.php.
 */
class FoodNavService
{
    /**
     * @return list<array{id: string, label: string, icon: string, group: string|null}>
     */
    public function itemsFor(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $hasFoodEmployees = class_exists(\App\Models\Employee::class);
        $canManageAnyFood = method_exists($user, 'canManageAnyFood') && $user->canManageAnyFood();
        $canManageNhanVien = method_exists($user, 'canManageFoodEmployees') && $user->canManageFoodEmployees();
        $canManageChamCong = method_exists($user, 'canManageFoodChamCong') && $user->canManageFoodChamCong();
        $canManageXinNghi = method_exists($user, 'canManageFoodXinNghi') && $user->canManageFoodXinNghi();
        $canManageUngLuong = method_exists($user, 'canManageFoodUngLuong') && $user->canManageFoodUngLuong();
        $canManageLuong = method_exists($user, 'canManageFoodLuong') && $user->canManageFoodLuong();
        $canViewFoodPayroll = method_exists($user, 'canViewFoodPayroll') && $user->canViewFoodPayroll();
        $canManageTongQuan = method_exists($user, 'canManageFoodTongQuan') && $user->canManageFoodTongQuan();
        $canManageDoanhSo = method_exists($user, 'canManageFoodDoanhSo') && $user->canManageFoodDoanhSo();
        $canManageSanPham = method_exists($user, 'canManageFoodSanPham') && $user->canManageFoodSanPham();
        $canManageBaoCao = method_exists($user, 'canManageFoodBaoCao') && $user->canManageFoodBaoCao();
        $canManageThongKeBuff = method_exists($user, 'canManageFoodThongKeBuff') && $user->canManageFoodThongKeBuff();
        $canCreateFoodBuffOrder = method_exists($user, 'canCreateFoodBuffOrder') && $user->canCreateFoodBuffOrder();
        $canManageFoodReviews = method_exists($user, 'canManageFoodReviews') && $user->canManageFoodReviews();
        $canViewCongNo = $user->is_admin || $canManageBaoCao;
        $isEmployee = $hasFoodEmployees
            && $user->employee
            && method_exists($user, 'canUseFoodEmployee')
            && $user->canUseFoodEmployee();
        $canUseQrChamCong = method_exists($user, 'canUseQrChamCong') && $user->canUseQrChamCong();

        $isOnlyThongKeBuff = ! $user->is_admin
            && $canManageThongKeBuff
            && ! $canCreateFoodBuffOrder
            && ! $canManageTongQuan
            && ! $canManageDoanhSo
            && ! $canManageSanPham
            && ! $canManageBaoCao
            && ! $canManageFoodReviews
            && ! $canManageNhanVien
            && ! $canManageChamCong
            && ! $canManageXinNghi
            && ! $canManageUngLuong
            && ! $canManageLuong
            && ! $canUseQrChamCong;
        $isOnlyReviews = method_exists($user, 'isFoodReviewsOnlyUser') && $user->isFoodReviewsOnlyUser();

        $navItems = [
            $this->item('tong-quan', 'Tổng quan', 'dashboard', 'tong-quan', $canManageTongQuan),
            $this->item('doanh-so', 'Doanh số', 'chart', 'tong-quan', $canManageDoanhSo),
            $this->item('san-pham', 'Sản phẩm', 'box', 'danh-muc', $canManageSanPham),
            $this->item('mon', 'Món', 'dish', 'danh-muc', $canManageSanPham && Route::has('food.mon')),
            $this->item('nguyen-lieu', 'Nguyên liệu', 'layers', 'danh-muc', $canManageSanPham && Route::has('food.nguyen-lieu')),
            $this->item('cong-thuc', 'Công thức', 'recipe', 'danh-muc', $canManageSanPham && Route::has('food.cong-thuc')),
            $this->item('chi-nhanh', 'Chi nhánh', 'store', 'danh-muc', $canManageBaoCao && Route::has('food.chi-nhanh')),
            $this->item('bao-cao-ban-hang', 'Báo cáo bán hàng', 'report', 'tong-quan', $canManageBaoCao),
            $this->item('thong-ke-buff', 'Thống kê seeding', 'stats', 'don-hang', $canManageThongKeBuff && Route::has('food.thong-ke-buff')),
            $this->item('lich-dat-don', 'Lịch đặt đơn', 'calendar', 'don-hang', $user->is_admin && $canManageThongKeBuff && Route::has('food.lich-dat-don')),
            $this->item('dat-don', 'Đặt đơn', 'order', 'don-hang', $canCreateFoodBuffOrder && Route::has('food.dat-don')),
            $this->item('lich-da-xac-nhan', 'Lịch đã xác nhận', 'calendar_check', 'don-hang', ($canCreateFoodBuffOrder || $canManageThongKeBuff) && Route::has('food.lich-da-xac-nhan')),
            $this->item('food-reviews', 'Đánh giá', 'star', 'don-hang', $canManageFoodReviews && Route::has('food.reviews.index')),
            $this->item('food-reviews-gift-attempts', 'Lịch sử nhận quà', 'list', 'don-hang', $user->is_admin && Route::has('food.reviews.gift-attempts')),
            $this->item('food-reviews-qr', 'QR nhận quà 5 sao', 'qr', 'don-hang', $user->is_admin && Route::has('food.qr-public-review-gift')),
            $this->item('khach-hang', 'Khách hàng', 'users', 'danh-muc', $canManageBaoCao),
            $this->item('cong-no', 'Công nợ', 'debt', 'tong-quan', ! $isEmployee && $canViewCongNo),
        ];

        if ($hasFoodEmployees) {
            $navItems[] = $this->item('nhan-vien', 'Nhân viên', 'users', 'nhan-su', $canManageNhanVien && Route::has('food.nhan-vien'));
            $navItems[] = $this->item('cham-cong', 'Chấm công', 'fingerprint', 'nhan-su', ($canManageChamCong || $isEmployee) && Route::has('food.cham-cong'));
            $navItems[] = $this->item('xin-nghi', 'Xin nghỉ', 'event_busy', 'nhan-su', ($canManageXinNghi || $isEmployee) && Route::has('food.xin-nghi'));
            $navItems[] = $this->item('ung-luong', 'Ứng lương', 'payments', 'nhan-su', ($canManageUngLuong || $isEmployee) && Route::has('food.ung-luong'));
            $navItems[] = $this->item('luong', 'Bảng lương', 'payroll', 'nhan-su', $canViewFoodPayroll && Route::has('food.luong'));
            $navItems[] = $this->item('luong-cua-toi', 'Lương của tôi', 'wallet', 'nhan-su', $isEmployee && ! $canManageLuong && Route::has('food.luong-cua-toi'));
        }

        if ($canUseQrChamCong && Route::has('food.qr-cham-cong')) {
            $navItems[] = $this->item('qr-cham-cong', 'QR chấm công', 'qr', 'nhan-su', true);
        }

        $navItems = array_values(array_filter($navItems, fn (array $item) => $item['show']));

        if (! $canManageAnyFood && ! $isEmployee) {
            if ($canUseQrChamCong) {
                $navItems = array_values(array_filter($navItems, fn (array $item) => $item['id'] === 'qr-cham-cong'));
            } else {
                $navItems = array_values(array_filter($navItems, fn (array $item) => $item['id'] === 'cong-no'));
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
            $navItems = array_values(array_filter(
                $navItems,
                fn (array $item) => in_array($item['id'], $onlyThongKeNavIds, true)
            ));
        }

        if ($isOnlyReviews) {
            $navItems = array_values(array_filter($navItems, fn (array $item) => $item['id'] === 'food-reviews'));
        }

        return array_map(static function (array $item) {
            unset($item['show']);

            return $item;
        }, $navItems);
    }

    /**
     * @return array{id: string, label: string, icon: string, group: string, show: bool}
     */
    private function item(string $id, string $label, string $icon, string $group, bool $show): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'icon' => $icon,
            'group' => $group,
            'show' => $show,
        ];
    }
}
