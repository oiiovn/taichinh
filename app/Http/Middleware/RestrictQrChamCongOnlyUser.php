<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictQrChamCongOnlyUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $hasOnlyQr = method_exists($user, 'canUseQrChamCong') && $user->canUseQrChamCong()
            && ! $user->is_admin
            && ! (method_exists($user, 'canManageAnyFood') && $user->canManageAnyFood())
            && ! (method_exists($user, 'canUseFoodEmployee') && $user->canUseFoodEmployee());
        $hasOnlyThongKeBuff = ! $user->is_admin
            && method_exists($user, 'canManageFoodThongKeBuff')
            && $user->canManageFoodThongKeBuff()
            && ! (method_exists($user, 'canManageFoodTongQuan') && $user->canManageFoodTongQuan())
            && ! (method_exists($user, 'canManageFoodDoanhSo') && $user->canManageFoodDoanhSo())
            && ! (method_exists($user, 'canManageFoodSanPham') && $user->canManageFoodSanPham())
            && ! (method_exists($user, 'canManageFoodBaoCao') && $user->canManageFoodBaoCao())
            && ! (method_exists($user, 'canManageFoodReviews') && $user->canManageFoodReviews())
            && ! (method_exists($user, 'canManageFoodEmployees') && $user->canManageFoodEmployees())
            && ! (method_exists($user, 'canManageFoodChamCong') && $user->canManageFoodChamCong())
            && ! (method_exists($user, 'canManageFoodXinNghi') && $user->canManageFoodXinNghi())
            && ! (method_exists($user, 'canManageFoodUngLuong') && $user->canManageFoodUngLuong())
            && ! (method_exists($user, 'canManageFoodLuong') && $user->canManageFoodLuong())
            && ! (method_exists($user, 'canUseFoodEmployee') && $user->canUseFoodEmployee())
            && ! (method_exists($user, 'canUseQrChamCong') && $user->canUseQrChamCong());

        if (! $hasOnlyQr && ! $hasOnlyThongKeBuff) {
            return $next($request);
        }

        $path = $request->path();
        if ($hasOnlyThongKeBuff) {
            if ($path === 'food/lich-dat-don' || str_starts_with($path, 'food/lich-dat-don/')) {
                return $next($request);
            }
            if ($path === 'food/thong-ke-buff' || str_starts_with($path, 'food/thong-ke-buff/')) {
                return $next($request);
            }
            if (in_array($path, ['food', 'food/danh-gia', 'food/danh-gia/import'], true)) {
                return $next($request);
            }

            return redirect()->route('food.thong-ke-buff');
        }

        $allowed = in_array($path, ['food', 'food/qr-cham-cong', 'food/qr-cham-cong/do', 'food/qr-cham-cong/refresh'], true);

        if ($allowed) {
            return $next($request);
        }

        return redirect()->route('food.qr-cham-cong');
    }
}
