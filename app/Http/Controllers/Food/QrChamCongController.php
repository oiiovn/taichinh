<?php

namespace App\Http\Controllers\Food;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QrChamCongController extends Controller
{
    public static function tokenForMinute(string $minuteKey): string
    {
        return hash_hmac('sha256', 'qr-cham-cong-' . $minuteKey, config('app.key'));
    }

    public static function validateToken(string $token): bool
    {
        $now = now();
        $current = $now->format('Y-m-d-H-i');
        if (hash_equals(self::tokenForMinute($current), $token)) {
            return true;
        }
        $prev = $now->copy()->subMinute()->format('Y-m-d-H-i');

        return hash_equals(self::tokenForMinute($prev), $token);
    }

    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        if (! $user->canUseQrChamCong()) {
            abort(403, 'Bạn không có quyền hiển thị QR chấm công.');
        }

        $minuteKey = now()->format('Y-m-d-H-i');
        $token = self::tokenForMinute($minuteKey);
        $scanUrl = route('food.qr-cham-cong.do', ['t' => $token]);
        $secondsUntilExpiry = (int) now()->diffInSeconds(now()->endOfMinute(), false);
        $secondsUntilExpiry = max(1, min(60, $secondsUntilExpiry));

        return view('pages.food.qr-cham-cong', [
            'title' => 'QR chấm công',
            'scanUrl' => $scanUrl,
            'secondsUntilExpiry' => $secondsUntilExpiry,
        ]);
    }

    /** Trả JSON để cập nhật QR không cần reload trang. */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->canUseQrChamCong()) {
            return response()->json(['ok' => false], 403);
        }

        $minuteKey = now()->format('Y-m-d-H-i');
        $token = self::tokenForMinute($minuteKey);
        $scanUrl = route('food.qr-cham-cong.do', ['t' => $token]);
        $secondsUntilExpiry = (int) now()->diffInSeconds(now()->endOfMinute(), false);
        $secondsUntilExpiry = max(1, min(60, $secondsUntilExpiry));

        return response()->json([
            'ok' => true,
            'scan_url' => $scanUrl,
            'seconds_until_expiry' => $secondsUntilExpiry,
        ]);
    }

    public function do(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập để chấm công.');
        }

        $token = $request->query('t', '');
        if (! self::validateToken($token)) {
            return redirect()->route('food.qr-cham-cong')->with('error', 'Mã QR hết hạn hoặc không hợp lệ. Vui lòng quét lại.');
        }

        $employee = $user->employee;
        if (! $employee) {
            return redirect()->route('food')->with('error', 'Bạn không phải nhân viên.');
        }
        if (! $user->canUseFoodEmployee()) {
            return redirect()->route('food')->with('error', 'Bạn chưa được cấp quyền chấm công.');
        }

        $today = Carbon::today();
        $log = AttendanceLog::query()->firstOrCreate(
            ['employee_id' => $employee->id, 'work_date' => $today],
            ['work_date' => $today]
        );

        $now = now();
        if (! $log->check_in_at) {
            $log->update(['check_in_at' => $now]);

            return redirect()->route('food.cham-cong')->with('success', 'Đã ghi nhận giờ vào ca.');
        }
        if (! $log->check_out_at) {
            $log->update(['check_out_at' => $now]);

            return redirect()->route('food.cham-cong')->with('success', 'Đã ghi nhận giờ ra ca.');
        }

        return redirect()->route('food.cham-cong')->with('info', 'Hôm nay bạn đã chấm đủ vào ca và ra ca.');
    }
}
