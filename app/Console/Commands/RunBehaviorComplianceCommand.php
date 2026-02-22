<?php

namespace App\Console\Commands;

use App\Services\BehaviorComplianceService;
use Illuminate\Console\Command;

class RunBehaviorComplianceCommand extends Command
{
    protected $signature = 'behavior:compliance';

    protected $description = 'Đánh giá compliance đề xuất vs hành vi 30 ngày sau; cập nhật execution_consistency_score.';

    public function handle(BehaviorComplianceService $service): int
    {
        $this->info('Đang chạy đánh giá compliance...');
        $service->runForAllUsers();
        $this->info('Xong.');
        return self::SUCCESS;
    }
}
