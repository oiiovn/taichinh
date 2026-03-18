<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanManageFoodBaoCao
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || (! $user->is_admin && ! $user->canManageFoodBaoCao())) {
            abort(403, 'Bạn không có quyền truy cập Báo cáo bán hàng.');
        }

        return $next($request);
    }
}
