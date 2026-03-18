<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanManageFoodSanPham
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || (! $user->is_admin && ! $user->canManageFoodSanPham())) {
            abort(403, 'Bạn không có quyền truy cập Sản phẩm.');
        }

        return $next($request);
    }
}
