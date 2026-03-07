<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TaiChinh\TaiChinhViewCache;
use Illuminate\Console\Command;

class ClearTaiChinhViewCacheCommand extends Command
{
    protected $signature = 'tai-chinh:clear-view-cache
                            {user_id? : ID user (bỏ trống = xóa cache toàn bộ user)}';

    protected $description = 'Xóa cache view trang Tài chính (tab Lịch thanh toán và các tab dùng chung cache).';

    public function handle(): int
    {
        $userId = $this->argument('user_id');

        if ($userId !== null && $userId !== '') {
            $userId = (int) $userId;
            $user = User::find($userId);
            if (! $user) {
                $this->error("User id {$userId} không tồn tại.");
                return 1;
            }
            TaiChinhViewCache::forget($userId);
            $this->info("Đã xóa cache view Tài chính cho user id {$userId}.");
            return 0;
        }

        $ids = User::pluck('id');
        $count = 0;
        foreach ($ids as $id) {
            TaiChinhViewCache::forget((int) $id);
            $count++;
        }
        $this->info("Đã xóa cache view Tài chính cho {$count} user.");
        return 0;
    }
}
