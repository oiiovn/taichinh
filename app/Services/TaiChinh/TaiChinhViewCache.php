<?php

namespace App\Services\TaiChinh;

use Illuminate\Support\Facades\Cache;

/**
 * Cache view data trang Tài chính.
 * - View: TTL 30 phút; stale (bản cũ) lưu riêng, dùng khi hết TTL để SWR.
 * - getSafe/putSafe: không ném lỗi khi mất kết nối cache.
 */
class TaiChinhViewCache
{
    /** TTL cache view chính (30 phút). */
    public const TTL_SECONDS = 1800; // 30 phút

    /** TTL stale — giữ bản cũ 2 ngày (tránh trả bản "Chưa đủ" quá lâu khi job warm chậm/lỗi). */
    public const TTL_STALE_SECONDS = 172800; // 2 ngày

    /** TTL cache insight/analytics/dashboard — tối đa 12h một lần. */
    public const TTL_HEAVY_SECONDS = 43200; // 12 giờ

    /** TTL riêng: insight 6h, analytics 10 phút, projection 5 phút. */
    public const TTL_INSIGHT_SECONDS = 21600;   // 6 giờ
    public const TTL_ANALYTICS_SECONDS = 600;  // 10 phút
    public const TTL_PROJECTION_SECONDS = 300; // 5 phút

    /** Financial context (debt/position) — reuse cho projection, analytics, insight, dashboard. */
    public const TTL_FINANCIAL_CONTEXT_SECONDS = 300; // 5 phút

    public static function key(int $userId): string
    {
        return 'tai_chinh_view_' . $userId;
    }

    /** Key lưu bản cũ (stale) để SWR — trả về ngay khi cache chính hết hạn. */
    public static function staleKey(int $userId): string
    {
        return 'tai_chinh_view_' . $userId . '_stale';
    }

    public static function insightKey(int $userId): string
    {
        return 'tai_chinh_insight_' . $userId;
    }

    public static function analyticsKey(int $userId): string
    {
        return 'tai_chinh_analytics_' . $userId;
    }

    public static function dashboardKey(int $userId): string
    {
        return 'tai_chinh_dashboard_' . $userId;
    }

    public static function projectionKey(int $userId): string
    {
        return 'tai_chinh_projection_' . $userId;
    }

    public static function financialContextKey(int $userId): string
    {
        return 'financial_context_' . $userId;
    }

    /**
     * TTL với jitter (± fraction) để tránh cache stampede khi nhiều user refresh cùng lúc.
     * Ví dụ: ttlWithJitter(600, 0.1) → 540–660 giây.
     */
    public static function ttlWithJitter(int $baseTtl, float $jitterFraction = 0.1): int
    {
        if ($baseTtl <= 0) {
            return $baseTtl;
        }
        $delta = (int) round($baseTtl * $jitterFraction);
        $delta = max(1, $delta);
        return $baseTtl + random_int(-$delta, $delta);
    }

    /** Lấy cache, trả null khi lỗi kết nối / mất cache — không ném exception. */
    public static function getSafe(string $key): mixed
    {
        try {
            return Cache::get($key);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Ghi cache, bỏ qua khi lỗi — không ném exception. */
    public static function putSafe(string $key, mixed $value, int $ttl): void
    {
        try {
            Cache::put($key, $value, $ttl);
        } catch (\Throwable $e) {
            // Mất kết nối cache: bỏ qua, request vẫn trả về dữ liệu tính được
        }
    }

    /** Lấy bản stale (bản cũ) theo user — dùng cho SWR. */
    public static function getStale(int $userId): mixed
    {
        return self::getSafe(self::staleKey($userId));
    }

    /** Ghi bản stale khi có dữ liệu mới (để lần sau cache hết hạn vẫn trả được bản cũ). */
    public static function putStale(int $userId, mixed $value): void
    {
        self::putSafe(self::staleKey($userId), $value, self::TTL_STALE_SECONDS);
    }

    public static function forget(int $userId): void
    {
        try {
            Cache::forget(self::key($userId));
            Cache::forget(self::staleKey($userId));
        } catch (\Throwable $e) {
            // Mất kết nối: bỏ qua, lần sau sẽ build lại
        }
    }

    /** Xóa cache insight + analytics + projection + dashboard (khi ?refresh_insight=1). */
    public static function forgetHeavy(int $userId): void
    {
        try {
            Cache::forget(self::insightKey($userId));
            Cache::forget(self::analyticsKey($userId));
            Cache::forget(self::projectionKey($userId));
            Cache::forget(self::dashboardKey($userId));
        } catch (\Throwable $e) {
            // Mất kết nối: bỏ qua
        }
    }

    /** Xóa cache financial context (khi user sửa nợ/vay, tài khoản). */
    public static function forgetFinancialContext(int $userId): void
    {
        try {
            Cache::forget(self::financialContextKey($userId));
        } catch (\Throwable $e) {
            // Mất kết nối: bỏ qua
        }
    }
}
