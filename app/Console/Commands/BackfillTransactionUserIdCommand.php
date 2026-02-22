<?php

namespace App\Console\Commands;

use App\Models\TransactionHistory;
use App\Services\Pay2sApiService;
use Illuminate\Console\Command;

class BackfillTransactionUserIdCommand extends Command
{
    protected $signature = 'transaction:backfill-user-id
                            {--dry-run : Chỉ in ra, không cập nhật DB}';

    protected $description = 'Gán user_id (và pay2s_bank_account_id nếu thiếu) cho giao dịch cũ, dựa theo STK đã lưu (account_number → pay2s_bank_accounts → user_bank_accounts).';

    public function handle(Pay2sApiService $pay2sService): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Chạy ở chế độ dry-run (không ghi DB).');
        }

        $query = TransactionHistory::whereNull('user_id');
        $total = $query->count();

        if ($total === 0) {
            $this->info('Không có giao dịch nào thiếu user_id.');
            return self::SUCCESS;
        }

        $this->info("Tìm thấy {$total} giao dịch chưa có user_id. Bắt đầu xử lý...");

        $updated = 0;
        $skipped = 0;
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->orderBy('id')->chunk(200, function ($transactions) use ($pay2sService, &$updated, &$skipped, $bar, $dryRun) {
            foreach ($transactions as $t) {
                $ids = $pay2sService->resolveAccountIdsForBackfill($t);

                if ($ids['user_id'] === null) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                if (! $dryRun) {
                    $t->user_id = $ids['user_id'];
                    if ($ids['pay2s_bank_account_id'] !== null) {
                        $t->pay2s_bank_account_id = $ids['pay2s_bank_account_id'];
                    }
                    $t->save();
                }
                $updated++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info($dryRun
            ? "Dry-run: sẽ cập nhật {$updated} giao dịch, bỏ qua {$skipped} (không resolve được user)."
            : "Đã gán user_id cho {$updated} giao dịch. Bỏ qua {$skipped} (không resolve được user từ STK)."
        );

        return self::SUCCESS;
    }
}
