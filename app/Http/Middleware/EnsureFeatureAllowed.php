<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureAllowed
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }
        if (! $user->canUseFeature($feature)) {
            $hasOnlyThongKeBuff = ! $user->is_admin
                && method_exists($user, 'canManageFoodThongKeBuff')
                && $user->canManageFoodThongKeBuff()
                && ! (method_exists($user, 'canManageFoodTongQuan') && $user->canManageFoodTongQuan())
                && ! (method_exists($user, 'canManageFoodDoanhSo') && $user->canManageFoodDoanhSo())
                && ! (method_exists($user, 'canManageFoodSanPham') && $user->canManageFoodSanPham())
                && ! (method_exists($user, 'canManageFoodBaoCao') && $user->canManageFoodBaoCao())
                && ! (method_exists($user, 'canManageFoodEmployees') && $user->canManageFoodEmployees())
                && ! (method_exists($user, 'canManageFoodChamCong') && $user->canManageFoodChamCong())
                && ! (method_exists($user, 'canManageFoodXinNghi') && $user->canManageFoodXinNghi())
                && ! (method_exists($user, 'canManageFoodUngLuong') && $user->canManageFoodUngLuong())
                && ! (method_exists($user, 'canManageFoodLuong') && $user->canManageFoodLuong())
                && ! (method_exists($user, 'canUseFoodEmployee') && $user->canUseFoodEmployee())
                && ! (method_exists($user, 'canUseQrChamCong') && $user->canUseQrChamCong());
            if ($hasOnlyThongKeBuff && \Illuminate\Support\Facades\Route::has('food.thong-ke-buff')) {
                return redirect()->route('food.thong-ke-buff');
            }
            abort(403, 'Bạn chưa được cấp quyền sử dụng tính năng này. Liên hệ quản trị viên.');
        }

        return $next($request);
    }
}
