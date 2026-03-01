<?php

namespace App\Services;

use App\Models\User;

class DepositorResolver
{
    /** Keyword trong mô tả -> user_id. Thứ tự VUONG, VAN, VU để tránh match nhầm. */
    protected static ?array $keywordToUserId = null;

    /**
     * Từ mô tả giao dịch (thường chứa tên người chuyển), trả về user_id người nạp hoặc null.
     */
    public static function resolveDepositorIdFromDescription(?string $description): ?int
    {
        if ($description === null || trim($description) === '') {
            return null;
        }
        $map = self::getKeywordToUserIdMap();
        $descUpper = mb_strtoupper($description);
        foreach (array_keys($map) as $keyword) {
            if (str_contains($descUpper, $keyword)) {
                return $map[$keyword];
            }
        }
        return null;
    }

    protected static function getKeywordToUserIdMap(): array
    {
        if (self::$keywordToUserId !== null) {
            return self::$keywordToUserId;
        }
        $users = User::whereIn('email', ['admin@gmail.com', 'vuong@gmail.com', 'van@gmail.com'])
            ->get()
            ->keyBy(fn ($u) => strtolower($u->email));
        self::$keywordToUserId = [
            'VUONG' => $users->get('vuong@gmail.com')?->id,
            'VAN' => $users->get('van@gmail.com')?->id,
            'VU' => $users->get('admin@gmail.com')?->id,
        ];
        return self::$keywordToUserId;
    }
}
