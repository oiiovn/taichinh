<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Mobile Food manager: can_manage_food_cham_cong (hoặc admin). */
class EnsureFoodMobileManager
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'error' => ['code' => 'UNAUTHENTICATED'],
            ], 401);
        }

        if (! $user->canManageFoodChamCong()) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chưa được cấp quyền quản lý chấm công.',
                'error' => ['code' => 'FORBIDDEN_MANAGE_CHAM_CONG'],
            ], 403);
        }

        return $next($request);
    }
}
