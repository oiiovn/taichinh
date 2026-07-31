<?php

namespace App\Http\Controllers\Food;

use App\Exceptions\Food\AttendanceException;
use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\FoodBranch;
use App\Services\Food\QrAttendanceToken;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QrChamCongController extends Controller
{
    /** @deprecated Prefer QrAttendanceToken — giữ để tương thích. */
    public static function tokenForMinute(string $minuteKey): string
    {
        return QrAttendanceToken::tokenForMinute($minuteKey);
    }

    /** @deprecated Prefer QrAttendanceToken::validateLegacy */
    public static function validateToken(string $token): bool
    {
        return QrAttendanceToken::validateLegacy($token);
    }

    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user && ! $user->canUseQrChamCong()) {
            abort(403, 'Bạn không có quyền hiển thị QR chấm công.');
        }

        $branch = $this->resolveOptionalBranch($request);
        $payload = $this->buildQrPayload($branch);

        return view('pages.food.qr-cham-cong', array_merge($payload, [
            'title' => 'QR chấm công',
            'embedPublic' => ! $user,
            'branch' => $branch,
        ]));
    }

    /** Trả JSON để cập nhật QR không cần reload trang. */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->canUseQrChamCong()) {
            return response()->json(['ok' => false], 403);
        }

        $branch = $this->resolveOptionalBranch($request);
        $payload = $this->buildQrPayload($branch);

        return response()->json([
            'ok' => true,
            'scan_url' => $payload['scanUrl'],
            'seconds_until_expiry' => $payload['secondsUntilExpiry'],
            'branch_id' => $branch?->id,
        ]);
    }

    public function do(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập để chấm công.');
        }

        $token = (string) $request->query('t', '');
        $branchId = $request->query('b');
        $branchId = $branchId !== null && $branchId !== '' ? (int) $branchId : null;

        if ($branchId) {
            try {
                QrAttendanceToken::assertValidForBranch($token, $branchId);
            } catch (AttendanceException $e) {
                return redirect()->route('food.qr-cham-cong', ['b' => $branchId])
                    ->with('error', $e->getMessage());
            }
        } elseif (! QrAttendanceToken::validateLegacy($token)) {
            return redirect()->route('food.qr-cham-cong')->with('error', 'Mã QR hết hạn hoặc không hợp lệ. Vui lòng quét lại.');
        }

        $employee = $user->employee;
        if (! $employee) {
            return redirect()->route('food')->with('error', 'Bạn không phải nhân viên.');
        }
        if (! $user->canUseFoodEmployee()) {
            return redirect()->route('food')->with('error', 'Bạn chưa được cấp quyền chấm công.');
        }

        // Web branch-aware: nếu có b thì NV phải được gán CN (cùng rule mobile, trừ GPS).
        if ($branchId) {
            $assigned = $employee->foodBranches()->where('food_branches.id', $branchId)->exists();
            if (! $assigned) {
                return redirect()->route('food.qr-cham-cong', ['b' => $branchId])
                    ->with('error', 'Bạn chưa được phân công chi nhánh này.');
            }
        }

        $today = Carbon::today();
        $log = AttendanceLog::query()->firstOrCreate(
            ['employee_id' => $employee->id, 'work_date' => $today],
            ['work_date' => $today]
        );

        $now = now();
        if (! $log->check_in_at) {
            $update = ['check_in_at' => $now];
            if ($branchId) {
                $update['food_branch_id'] = $branchId;
                $update['check_in_method'] = 'qr';
            }
            $log->update($update);
            $log->refresh();
            $employee->applyLatePenaltyNote($log);

            return redirect()->route('food.cham-cong')->with('success', 'Đã ghi nhận giờ vào ca.');
        }
        if (! $log->check_out_at) {
            if ($branchId && $log->food_branch_id !== null && (int) $log->food_branch_id !== $branchId) {
                return redirect()->route('food.qr-cham-cong', ['b' => $branchId])
                    ->with('error', 'Phải checkout tại cùng chi nhánh đã check-in.');
            }
            $update = ['check_out_at' => $now];
            if ($branchId) {
                $update['food_branch_id'] = $log->food_branch_id ?? $branchId;
                $update['check_out_method'] = 'qr';
            }
            $log->update($update);

            return redirect()->route('food.cham-cong')->with('success', 'Đã ghi nhận giờ ra ca.');
        }

        return redirect()->route('food.cham-cong')->with('info', 'Hôm nay bạn đã chấm đủ vào ca và ra ca.');
    }

    protected function resolveOptionalBranch(Request $request): ?FoodBranch
    {
        $b = $request->query('b');
        if ($b === null || $b === '') {
            return null;
        }

        $branch = FoodBranch::query()->find((int) $b);
        if (! $branch) {
            abort(404, 'Chi nhánh không tồn tại.');
        }

        return $branch;
    }

    /**
     * @return array{scanUrl: string, secondsUntilExpiry: int, refreshUrl: string}
     */
    protected function buildQrPayload(?FoodBranch $branch): array
    {
        if ($branch) {
            $token = QrAttendanceToken::makeForBranch((int) $branch->id);
            $scanUrl = route('food.qr-cham-cong.do', ['t' => $token, 'b' => $branch->id]);
            $refreshUrl = route('food.qr-cham-cong.refresh', ['b' => $branch->id]);
        } else {
            $token = QrAttendanceToken::makeLegacy();
            $scanUrl = route('food.qr-cham-cong.do', ['t' => $token]);
            $refreshUrl = route('food.qr-cham-cong.refresh');
        }

        $secondsUntilExpiry = (int) now()->diffInSeconds(now()->endOfMinute(), false);
        $secondsUntilExpiry = max(1, min(60, $secondsUntilExpiry));

        return [
            'scanUrl' => $scanUrl,
            'secondsUntilExpiry' => $secondsUntilExpiry,
            'refreshUrl' => $refreshUrl,
        ];
    }
}
