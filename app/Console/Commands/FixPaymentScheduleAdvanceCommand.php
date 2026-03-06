<?php

namespace App\Console\Commands;

use App\Models\PaymentSchedule;
use App\Services\PaymentScheduleMatchService;
use Illuminate\Console\Command;

class FixPaymentScheduleAdvanceCommand extends Command
{
    protected $signature = 'payment-schedule:fix-advance
                            {--user= : Chỉ xử lý user_id (bỏ trống = tất cả)}';

    protected $description = 'Ép gia hạn hạn kế tiếp từ last_paid_date cho lịch đã match nhưng chưa advance (vd. Wifi FPT vẫn 12/03 sau khi đã thanh toán).';

    public function handle(PaymentScheduleMatchService $matchService): int
    {
        $userId = $this->option('user');
        $query = PaymentSchedule::whereNotNull('last_paid_date')
            ->where('status', PaymentSchedule::STATUS_ACTIVE);
        if ($userId !== null && $userId !== '') {
            $query->where('user_id', (int) $userId);
        }
        $schedules = $query->get();
        $fixed = 0;
        foreach ($schedules as $schedule) {
            try {
                if ($matchService->advanceNextDueFromLastPaid($schedule)) {
                    $fixed++;
                    $this->line(sprintf(
                        '  Đã gia hạn: lịch "%s" (id %d) → next_due_date %s',
                        $schedule->name,
                        $schedule->id,
                        $schedule->fresh()->next_due_date?->format('d/m/Y')
                    ));
                }
            } catch (\Throwable $e) {
                $this->warn("  Lịch #{$schedule->id}: " . $e->getMessage());
            }
        }
        $this->info("Đã kiểm tra " . $schedules->count() . " lịch; cập nhật hạn kế tiếp: {$fixed}.");
        return self::SUCCESS;
    }
}
