<?php

namespace App\Console\Commands;

use App\Models\TransactionHistory;
use App\Services\MerchantKeyNormalizer;
use Illuminate\Console\Command;

class RebuildMerchantKeysCommand extends Command
{
    protected $signature = 'transaction:rebuild-merchant-keys
                            {--dry-run : Chỉ in ra, không ghi DB}';

    protected $description = 'Rebuild merchant_key cho toàn bộ giao dịch theo normalizer mới (stable_merchant_identifier). Chạy sau khi sửa MerchantKeyNormalizer.';

    public function handle(MerchantKeyNormalizer $normalizer): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Chạy ở chế độ dry-run (không ghi DB).');
        }

        $total = TransactionHistory::count();
        if ($total === 0) {
            $this->info('Không có giao dịch.');
            return self::SUCCESS;
        }

        $this->info("Rebuild merchant_key cho {$total} giao dịch...");

        $updated = 0;
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        TransactionHistory::orderBy('id')->chunk(200, function ($transactions) use ($normalizer, &$updated, $bar, $dryRun) {
            foreach ($transactions as $t) {
                $newKey = $normalizer->normalize($t->description);
                if (! $dryRun && $t->merchant_key !== $newKey) {
                    $t->merchant_key = $newKey;
                    $t->save();
                    $updated++;
                } elseif ($dryRun && $t->merchant_key !== $newKey) {
                    $updated++;
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info($dryRun
            ? "Dry-run: sẽ cập nhật {$updated} giao dịch có merchant_key khác sau khi rebuild."
            : "Đã rebuild merchant_key. Cập nhật {$updated} giao dịch."
        );

        return self::SUCCESS;
    }
}
