<?php

namespace App\Console\Commands;

use App\Services\Pay2sApiService;
use Illuminate\Console\Command;

class SyncPay2sCommand extends Command
{
    protected $signature = 'pay2s:sync
                            {--loop=0 : Số lần lặp (0 = chạy 1 lần)}
                            {--interval=5 : Số giây nghỉ giữa mỗi lần (mặc định 5)}';

    protected $description = 'Đồng bộ tài khoản ngân hàng và giao dịch từ Pay2s API vào bảng tổng lịch sử giao dịch.';

    public function handle(Pay2sApiService $service): int
    {
        $loop = (int) $this->option('loop');
        $iterations = $loop > 0 ? $loop : 1;
        $intervalSeconds = max(1, (int) $this->option('interval'));

        for ($i = 0; $i < $iterations; $i++) {
            if ($i > 0) {
                $this->info("Chờ {$intervalSeconds} giây...");
                sleep($intervalSeconds);
            }

            $result = $service->sync();

            if (! empty($result['errors'])) {
                foreach ($result['errors'] as $err) {
                    $this->warn($err);
                }
            }
            $this->info(sprintf(
                'Sync lần %d: %d tài khoản, %d giao dịch mới.',
                $i + 1,
                $result['accounts'],
                $result['transactions']
            ));
        }

        return self::SUCCESS;
    }
}
