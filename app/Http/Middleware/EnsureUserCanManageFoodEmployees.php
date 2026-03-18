<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanManageFoodEmployees
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || (! $user->canManageFoodEmployees() && ! $user->canManageFoodLuong())) {
            abort(403, 'Bạn không có quyền truy cập mục này.');
        }

        return $next($request);
    }
}
