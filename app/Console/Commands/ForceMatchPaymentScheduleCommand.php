<?php

namespace App\Console\Commands;

use App\Models\PaymentSchedule;
use App\Models\TransactionHistory;
use App\Services\PaymentScheduleMatchService;
use App\Services\TaiChinh\TaiChinhViewCache;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ForceMatchPaymentScheduleCommand extends Command
{
    protected $signature = 'payment-schedule:force-match
                            {schedule : Tên hoặc ID lịch (vd. "Wifi FPT")}
                            {--tx-id= : ID giao dịch cần gán vào lịch}
                            {--pattern= : Mô tả chứa chuỗi này (vd. SGACX2862), dùng khi không chỉ định --tx-id}
                            {--amount= : Số tiền (dương, vd. 330000), dùng cùng --pattern}
                            {--days=60 : Số ngày giao dịch OUT gần đây khi tìm theo --pattern}';

    protected $description = 'Ép gán giao dịch vào lịch (gia hạn next_due_date), bỏ qua cửa sổ match.';

    public function handle(PaymentScheduleMatchService $matchService): int
    {
        $scheduleInput = $this->argument('schedule');
        $txId = $this->option('tx-id');
        $pattern = $this->option('pattern');
        $amount = $this->option('amount');
        $days = max(1, (int) $this->option('days'));

        $schedule = is_numeric($scheduleInput)
            ? PaymentSchedule::find((int) $scheduleInput)
            : PaymentSchedule::where('name', 'like', '%' . $scheduleInput . '%')->first();

        if (! $schedule) {
            $this->error('Không tìm thấy lịch: ' . $scheduleInput);
            return 1;
        }

        if ($txId !== null && $txId !== '') {
            $transaction = TransactionHistory::where('type', 'OUT')->find((int) $txId);
        } elseif ($pattern !== null && $pattern !== '') {
            $since = Carbon::now()->subDays($days)->startOfDay();
            $query = TransactionHistory::where('type', 'OUT')
                ->where('user_id', $schedule->user_id)
                ->where('transaction_date', '>=', $since)
                ->where('description', 'like', '%' . $pattern . '%');
            if ($amount !== null && $amount !== '') {
                $amt = (float) str_replace(',', '', $amount);
                $query->whereRaw('ABS(amount) BETWEEN ? AND ?', [$amt * 0.9, $amt * 1.1]);
            }
            $transaction = $query->orderByDesc('transaction_date')->first();
        } else {
            $this->error('Cần --tx-id=... hoặc --pattern=... (có thể kèm --amount=)');
            return 1;
        }

        if (! $transaction) {
            $this->error('Không tìm thấy giao dịch OUT phù hợp.');
            return 1;
        }

        $matchService->forceMatch($schedule, $transaction);
        $schedule->refresh();
        TaiChinhViewCache::forget($schedule->user_id);
        $this->info(sprintf(
            'Đã ép match: tx #%d %s %s → lịch "%s" (id %d). Hạn kế tiếp: %s',
            $transaction->id,
            $transaction->transaction_date?->format('Y-m-d'),
            number_format((float) $transaction->amount),
            $schedule->name,
            $schedule->id,
            $schedule->next_due_date?->format('d/m/Y')
        ));
        return 0;
    }
}
