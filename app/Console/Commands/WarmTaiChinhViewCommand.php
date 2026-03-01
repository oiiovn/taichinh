<?php

namespace App\Console\Commands;

use App\Jobs\WarmTaiChinhViewJob;
use App\Models\User;
use Illuminate\Console\Command;

class WarmTaiChinhViewCommand extends Command
{
    protected $signature = 'tai-chinh:warm-view
                            {--limit=30 : Số user tối đa mỗi lần chạy}
                            {--all : Làm ấm cho mọi user có tài khoản ngân hàng}';

    protected $description = 'Làm ấm cache view trang Tài chính (Chiến lược, insight) cho user có liên kết tài khoản — chạy định kỳ 15 phút.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $all = $this->option('all');
        $query = User::query()->whereHas('userBankAccounts')->select('id');
        if (! $all && $limit > 0) {
            $query->limit($limit);
        }
        $userIds = $query->pluck('id')->all();
        $count = 0;
        foreach ($userIds as $userId) {
            WarmTaiChinhViewJob::dispatch($userId);
            $count++;
        }
        if ($count > 0) {
            $this->info("Đã dispatch {$count} job làm ấm cache.");
        }
        return self::SUCCESS;
    }
}
