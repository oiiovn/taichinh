<?php

namespace App\Http\Middleware;

use App\Models\User;
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
            if ($user instanceof User && $user->isFoodThongKeBuffOnlyUser() && \Illuminate\Support\Facades\Route::has('food.thong-ke-buff')) {
                if ($feature === 'food') {
                    $path = $request->path();
                    if ($path === 'food' || str_starts_with($path, 'food/thong-ke-buff')) {
                        return $next($request);
                    }
                    if ($user->canUseFoodEmployee()) {
                        foreach (['food/cham-cong', 'food/xin-nghi', 'food/ung-luong', 'food/luong-cua-toi'] as $prefix) {
                            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                                return $next($request);
                            }
                        }
                    }
                    if ($user->canCreateFoodBuffOrder()) {
                        foreach (['food/dat-don', 'food/lich-da-xac-nhan'] as $prefix) {
                            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                                return $next($request);
                            }
                        }
                    }
                }

                return redirect()->route('food.thong-ke-buff');
            }
            if ($user instanceof User && $user->isFoodBuffOrderOnlyUser() && \Illuminate\Support\Facades\Route::has('food.dat-don')) {
                if ($feature === 'food') {
                    $path = $request->path();
                    if ($path === 'food' || str_starts_with($path, 'food/dat-don')) {
                        return $next($request);
                    }
                }

                return redirect()->route('food.dat-don');
            }
            if ($user instanceof User && $user->canManageFoodReviews() && \Illuminate\Support\Facades\Route::has('food.reviews.index')) {
                if ($feature === 'food') {
                    $path = $request->path();
                    if ($user->canAccessFoodReviewsPath($path)) {
                        return $next($request);
                    }
                }

                return redirect()->route('food.reviews.index');
            }
            abort(403, 'Bạn chưa được cấp quyền sử dụng tính năng này. Liên hệ quản trị viên.');
        }

        return $next($request);
    }
}
