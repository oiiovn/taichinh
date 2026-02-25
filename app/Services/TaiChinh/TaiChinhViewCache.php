<?php

namespace App\Services\TaiChinh;

use Illuminate\Support\Facades\Cache;

/**
 * Cache view data trang Tài chính.
 * - View: TTL 1 giờ, invalidate khi user ghi dữ liệu.
 * - Insight / analytics / dashboard: TTL 12 giờ, invalidate khi ?refresh_insight=1.
 */
class TaiChinhViewCache
{
    public const TTL_SECONDS = 3600; // 1 giờ

    /** TTL cache insight/analytics/dashboard — tối đa 12h một lần. */
    public const TTL_HEAVY_SECONDS = 43200; // 12 giờ

    public static function key(int $userId): string
    {
        return 'tai_chinh_view_' . $userId;
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

    public static function forget(int $userId): void
    {
        Cache::forget(self::key($userId));
    }

    /** Xóa cache insight + analytics + dashboard (khi ?refresh_insight=1). */
    public static function forgetHeavy(int $userId): void
    {
        Cache::forget(self::insightKey($userId));
        Cache::forget(self::analyticsKey($userId));
        Cache::forget(self::dashboardKey($userId));
    }
}
