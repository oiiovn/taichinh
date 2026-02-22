<?php

namespace App\Console\Commands;

use App\Jobs\AccrueLoanInterestJob;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AccrueLoanInterestDaysCommand extends Command
{
    protected $signature = 'loans:accrue-days
                            {days=5 : Số ngày cần tạo lãi}
                            {--contract= : ID hợp đồng (bỏ trống = tất cả active)}';

    protected $description = 'Chạy tạo lãi tự động cho N ngày tới (để xem khoản vay đã chạy lãi vài ngày).';

    public function handle(): int
    {
        $days = max(1, (int) $this->argument('days'));
        $contractId = $this->option('contract') ? (int) $this->option('contract') : null;

        $this->info("Chạy tạo lãi cho {$days} ngày tới" . ($contractId ? " (hợp đồng #{$contractId})" : ' (tất cả hợp đồng active)'));

        for ($i = 0; $i < $days; $i++) {
            $asOf = Carbon::today()->addDays($i)->format('Y-m-d');
            $this->line("  Ngày " . ($i + 1) . "/{$days}: asOf = {$asOf}");
            $job = new AccrueLoanInterestJob($contractId, $asOf);
            $job->handle(
                app(\App\Services\LoanLedgerService::class),
                app(\App\Services\LoanInterestCalculator::class)
            );
        }

        $this->info('Xong. Kiểm tra Lịch sử giao dịch trên trang Chi tiết hợp đồng.');
        return self::SUCCESS;
    }
}
