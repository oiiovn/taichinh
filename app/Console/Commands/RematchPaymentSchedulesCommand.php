<?php

namespace App\Console\Commands;

use App\Models\TransactionHistory;
use App\Services\PaymentScheduleMatchService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RematchPaymentSchedulesCommand extends Command
{
    protected $signature = 'payment-schedule:rematch
                            {--days=30 : Số ngày giao dịch OUT gần đây để thử match lại}';

    protected $description = 'Chạy lại match giao dịch → lịch thanh toán (sửa trường hợp số tiền âm không match, gia hạn hạn kế tiếp).';

    public function handle(PaymentScheduleMatchService $matchService): int
    {
        $days = max(1, (int) $this->option('days'));
        $since = Carbon::now()->subDays($days)->startOfDay();

        $query = TransactionHistory::where('type', 'OUT')
            ->whereNotNull('user_id')
            ->where('transaction_date', '>=', $since);

        $total = $query->count();
        if ($total === 0) {
            $this->info("Không có giao dịch OUT nào trong {$days} ngày qua.");
            return self::SUCCESS;
        }

        $matched = 0;
        $query->orderBy('id')->chunk(100, function ($transactions) use ($matchService, &$matched) {
            foreach ($transactions as $tx) {
                try {
                    $schedule = $matchService->tryMatch($tx);
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
