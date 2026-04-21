<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanCreateFoodBuffOrder
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || (! $user->is_admin && ! $user->canCreateFoodBuffOrder())) {
            abort(403, 'Bạn không có quyền tạo đơn Food thủ công.');
        }

        return $next($request);
    }
}
