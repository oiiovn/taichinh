<?php

namespace App\Console\Commands;

use App\Models\FinancialStateSnapshot;
use Illuminate\Console\Command;

class ClearStrategyTimelineCommand extends Command
{
    protected $signature = 'strategy:clear-timeline
                            {--user= : Chỉ xóa snapshot của user_id này}
                            {--force : Không hỏi xác nhận khi xóa toàn bộ}';

    protected $description = 'Xóa toàn bộ dữ liệu tiến trình (Hành trình) để chạy lại từ đầu. Mặc định xóa tất cả user.';

    public function handle(): int
    {
        $userId = $this->option('user');
        $force = $this->option('force');

        $query = FinancialStateSnapshot::query();
        if ($userId !== null && $userId !== '') {
            $query->where('user_id', (int) $userId);
        }

        $count = $query->count();
        if ($count === 0) {
            $this->info('Không có bản ghi nào để xóa.');
            return self::SUCCESS;
        }

        if (! $force && $userId === null) {
            if (! $this->confirm("Sẽ xóa {$count} snapshot của tất cả user. Tiếp tục?")) {
                return self::SUCCESS;
            }
        }

        $deleted = $query->delete();
        $this->info("Đã xóa {$deleted} bản ghi tiến trình." . ($userId !== null && $userId !== '' ? " (user_id={$userId})" : ' (toàn bộ user)'));

        return self::SUCCESS;
    }
}
