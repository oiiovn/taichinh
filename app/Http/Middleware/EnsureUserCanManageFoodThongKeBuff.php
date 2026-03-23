<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanManageFoodThongKeBuff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || (! $user->is_admin && ! $user->canManageFoodThongKeBuff())) {
            abort(403, 'Bạn không có quyền truy cập Thống kê seeding.');
        }

        return $next($request);
    }
}
