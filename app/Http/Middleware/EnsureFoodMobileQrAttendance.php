<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Mobile QR attendance: cần thêm can_use_qr_cham_cong. */
class EnsureFoodMobileQrAttendance
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->canUseQrChamCong()) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chưa được cấp quyền chấm công bằng QR.',
                'error' => ['code' => 'FORBIDDEN_QR_CHAM_CONG'],
            ], 403);
        }

        return $next($request);
    }
}
