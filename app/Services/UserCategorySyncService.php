<?php

namespace App\Services;

use App\Models\SystemCategory;
use App\Models\User;
use App\Models\UserCategory;

class UserCategorySyncService
{
    /**
     * Đảm bảo user có đủ user_categories từ system_categories (tạo thiếu nếu chưa có).
     */
    public function ensureUserHasSystemCategories(User $user): void
    {
        $systemIds = SystemCategory::pluck('id')->all();
        if (empty($systemIds)) {
            return;
        }
        $existing = UserCategory::where('user_id', $user->id)
            ->whereNotNull('based_on_system_category_id')
            ->whereIn('based_on_system_category_id', $systemIds)
            ->pluck('based_on_system_category_id')
            ->all();
        $missing = array_diff($systemIds, $existing);
        if (empty($missing)) {
            return;
        }
        $systemCategories = SystemCategory::whereIn('id', $missing)->get();
        foreach ($systemCategories as $sc) {
            UserCategory::create([
                'user_id' => $user->id,
                'name' => $sc->name,
                'type' => $sc->type,
                'based_on_system_category_id' => $sc->id,
            ]);
        }
    }
}
