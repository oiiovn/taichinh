<?php

namespace App\Services\TaiChinh;

use Illuminate\Support\Facades\Cache;

/**
 * Cache view data trang Tài chính (TTL 2 phút).
 * Invalidate khi user thay đổi dữ liệu (giao dịch, lịch, ngưỡng, tài khoản, nợ, ...).
 */
class TaiChinhViewCache
{
    public const TTL_SECONDS = 120;

    public static function key(int $userId): string
    {
        return 'tai_chinh_view_' . $userId;
    }

    public static function forget(int $userId): void
    {
        Cache::forget(self::key($userId));
    }
}
