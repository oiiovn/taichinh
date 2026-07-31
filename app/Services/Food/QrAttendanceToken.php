<?php

namespace App\Services\Food;

use App\Exceptions\Food\AttendanceException;
use Carbon\CarbonInterface;

/**
 * QR chấm công: HMAC-SHA256 theo phút (legacy + branch-aware).
 * Cùng thuật toán với QrChamCongController — không tạo cơ chế song song.
 */
class QrAttendanceToken
{
    public const PREFIX = 'qr-cham-cong-';

    public static function minuteKey(?CarbonInterface $at = null): string
    {
        return ($at ?? now())->format('Y-m-d-H-i');
    }

    /** Legacy: HMAC(prefix + minuteKey). */
    public static function tokenForMinute(string $minuteKey): string
    {
        return hash_hmac('sha256', self::PREFIX.$minuteKey, (string) config('app.key'));
    }

    /** Branch-aware: HMAC(prefix + branchId + '-' + minuteKey). */
    public static function tokenForBranchMinute(int $branchId, string $minuteKey): string
    {
        return hash_hmac(
            'sha256',
            self::PREFIX.$branchId.'-'.$minuteKey,
            (string) config('app.key')
        );
    }

    public static function makeLegacy(?CarbonInterface $at = null): string
    {
        return self::tokenForMinute(self::minuteKey($at));
    }

    public static function makeForBranch(int $branchId, ?CarbonInterface $at = null): string
    {
        return self::tokenForBranchMinute($branchId, self::minuteKey($at));
    }

    /** Legacy: current hoặc previous minute. */
    public static function validateLegacy(string $token, ?CarbonInterface $at = null): bool
    {
        $now = $at ?? now();
        $current = self::minuteKey($now);
        if (hash_equals(self::tokenForMinute($current), $token)) {
            return true;
        }
        $prev = self::minuteKey($now->copy()->subMinute());

        return hash_equals(self::tokenForMinute($prev), $token);
    }

    /**
     * Branch-aware: token khớp branch + current/previous minute.
     *
     * @throws AttendanceException INVALID_QR | EXPIRED_QR
     */
    public static function assertValidForBranch(string $token, int $branchId, ?CarbonInterface $at = null): void
    {
        $now = $at ?? now();
        $current = self::minuteKey($now);
        $expectedCurrent = self::tokenForBranchMinute($branchId, $current);
        if (hash_equals($expectedCurrent, $token)) {
            return;
        }

        $prev = self::minuteKey($now->copy()->subMinute());
        $expectedPrev = self::tokenForBranchMinute($branchId, $prev);
        if (hash_equals($expectedPrev, $token)) {
            return;
        }

        // Token khớp phút cũ hơn (2–10 phút) → hết hạn; còn lại → không hợp lệ.
        for ($i = 2; $i <= 10; $i++) {
            $oldKey = self::minuteKey($now->copy()->subMinutes($i));
            if (hash_equals(self::tokenForBranchMinute($branchId, $oldKey), $token)) {
                throw AttendanceException::make(
                    AttendanceException::EXPIRED_QR,
                    'Mã QR đã hết hạn. Vui lòng quét lại.',
                    422
                );
            }
        }

        throw AttendanceException::make(
            AttendanceException::INVALID_QR,
            'Mã QR không hợp lệ.',
            422
        );
    }
}
