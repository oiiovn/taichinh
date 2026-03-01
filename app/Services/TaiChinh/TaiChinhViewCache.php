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

    /** TTL stale — giữ bản cũ lâu để luôn có dữ liệu hiển thị khi cache hết hạn. */
    public const TTL_STALE_SECONDS = 604800; // 7 ngày

    /** TTL cache insight/analytics/dashboard — tối đa 12h một lần. */
    public const TTL_HEAVY_SECONDS = 43200; // 12 giờ

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

    /** Xóa cache insight + analytics + dashboard (khi ?refresh_insight=1). */
    public static function forgetHeavy(int $userId): void
    {
        try {
            Cache::forget(self::insightKey($userId));
            Cache::forget(self::analyticsKey($userId));
            Cache::forget(self::dashboardKey($userId));
        } catch (\Throwable $e) {
            // Mất kết nối: bỏ qua
        }
    }
}
