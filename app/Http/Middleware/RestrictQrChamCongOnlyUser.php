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

        if (! $hasOnlyQr) {
            return $next($request);
        }

        $path = $request->path();
        $allowed = in_array($path, ['food', 'food/qr-cham-cong', 'food/qr-cham-cong/do', 'food/qr-cham-cong/refresh'], true);

        if ($allowed) {
            return $next($request);
        }

        return redirect()->route('food.qr-cham-cong');
    }
}
