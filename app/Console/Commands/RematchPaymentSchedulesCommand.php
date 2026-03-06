<?php

namespace App\Console\Commands;

use App\Models\TransactionHistory;
use App\Services\Pay2sApiService;
use App\Services\PaymentScheduleMatchService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RematchPaymentSchedulesCommand extends Command
{
    protected $signature = 'payment-schedule:rematch
                            {--days=30 : Số ngày giao dịch OUT gần đây để thử match lại}
                            {--backfill : Gán user_id cho giao dịch chưa có rồi mới match}
                            {--unmatch-tx= : ID giao dịch cần gỡ khỏi lịch hiện tại (để match lại đúng lịch)}';

    protected $description = 'Chạy lại match giao dịch → lịch thanh toán (sửa trường hợp số tiền âm không match, gia hạn hạn kế tiếp).';

    public function handle(PaymentScheduleMatchService $matchService, Pay2sApiService $pay2s): int
    {
        $days = max(1, (int) $this->option('days'));
        $withBackfill = $this->option('backfill');
        $unmatchTxId = $this->option('unmatch-tx');
        $since = Carbon::now()->subDays($days)->startOfDay();

        if ($unmatchTxId !== null && $unmatchTxId !== '') {
            $cleared = \App\Models\PaymentSchedule::where('last_matched_transaction_id', (int) $unmatchTxId)->update(['last_matched_transaction_id' => null, 'last_paid_date' => null]);
            $this->info("Đã gỡ tx #{$unmatchTxId} khỏi {$cleared} lịch (sẽ match lại).");
        }

        if ($withBackfill) {
            $filled = $pay2s->backfillUserIdsForPendingTransactions();
            $this->info("Backfill: đã gán user_id cho {$filled} giao dịch.");
        }

        $query = TransactionHistory::where('type', 'OUT')
            ->where('transaction_date', '>=', $since);
        if (! $withBackfill) {
            $query->whereNotNull('user_id');
        }

        $total = $query->count();
        if ($total === 0) {
            $this->info("Không có giao dịch OUT nào trong {$days} ngày qua.");
            return self::SUCCESS;
        }

        $matched = 0;
        $query->orderBy('id')->chunk(100, function ($transactions) use ($matchService, &$matched) {
            foreach ($transactions as $tx) {
                if (! $tx->user_id) {
                    continue;
                }
                try {
                    $schedule = $matchService->tryMatch($tx->fresh());
                    if ($schedule !== null) {
                        $matched++;
                        $this->line(sprintf(
                            '  Match: tx #%d %s %s → lịch "%s" (id %d)',
                            $tx->id,
                            $tx->transaction_date?->format('Y-m-d'),
                            number_format((float) $tx->amount),
                            $schedule->name,
                            $schedule->id
                        ));
                    }
                } catch (\Throwable $e) {
                    $this->warn("  Tx #{$tx->id}: " . $e->getMessage());
                }
            }
        });

        $this->info("Đã xử lý giao dịch OUT trong {$days} ngày; match mới: {$matched}.");
        return self::SUCCESS;
    }
}
