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
            abort(403, 'Bạn chưa được cấp quyền sử dụng tính năng này. Liên hệ quản trị viên.');
        }

        return $next($request);
    }
}
