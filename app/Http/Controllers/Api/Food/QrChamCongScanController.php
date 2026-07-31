<?php

namespace App\Http\Controllers\Api\Food;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Food\QrChamCongController as WebQrChamCongController;
use App\Models\AttendanceLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Nhân viên quét QR chấm công từ app (Sanctum).
 */
class QrChamCongScanController extends Controller
{
    public function scan(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            't' => ['required', 'string', 'max:128'],
        ]);

        $token = trim((string) $validated['t']);
        // Cho phép gửi full URL quét được từ camera.
        if (str_contains($token, '://') || str_contains($token, 'qr-cham-cong')) {
            $parsed = parse_url($token);
            if (! empty($parsed['query'])) {
                parse_str($parsed['query'], $query);
                if (! empty($query['t'])) {
                    $token = (string) $query['t'];
                }
            }
        }

        if (! WebQrChamCongController::validateToken($token)) {
            return response()->json([
                'ok' => false,
                'message' => 'Mã QR hết hạn hoặc không hợp lệ. Vui lòng quét lại.',
            ], 422);
        }

        $employee = $user->employee;
        if (! $employee) {
            return response()->json([
                'ok' => false,
                'message' => 'Bạn không phải nhân viên.',
            ], 403);
        }
        if (! $user->canUseFoodEmployee()) {
            return response()->json([
                'ok' => false,
                'message' => 'Bạn chưa được cấp quyền chấm công.',
            ], 403);
        }

        $today = Carbon::today();
        $log = AttendanceLog::query()->firstOrCreate(
            ['employee_id' => $employee->id, 'work_date' => $today],
            ['work_date' => $today]
        );

        $now = now();
        if (! $log->check_in_at) {
            $log->update(['check_in_at' => $now]);
            $log->refresh();
            $employee->applyLatePenaltyNote($log);

            return response()->json([
                'ok' => true,
                'action' => 'check_in',
                'message' => 'Đã ghi nhận giờ vào ca.',
                'check_in_at' => $log->check_in_at?->format('H:i'),
            ]);
        }

        if (! $log->check_out_at) {
            $log->update(['check_out_at' => $now]);
            $log->refresh();

            return response()->json([
                'ok' => true,
                'action' => 'check_out',
                'message' => 'Đã ghi nhận giờ ra ca.',
                'check_out_at' => $log->check_out_at?->format('H:i'),
            ]);
        }

        return response()->json([
            'ok' => true,
            'action' => 'done',
            'message' => 'Hôm nay bạn đã chấm đủ vào ca và ra ca.',
        ]);
    }
}
