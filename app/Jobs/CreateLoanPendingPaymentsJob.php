<?php

namespace App\Jobs;

use App\Models\LoanContract;
use App\Services\LoanPendingPaymentService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateLoanPendingPaymentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?int $contractId = null) {}

    public function handle(LoanPendingPaymentService $service): void
    {
        $today = Carbon::today();

        $query = LoanContract::query()
            ->where('status', LoanContract::STATUS_ACTIVE)
            ->where('payment_schedule_enabled', true)
            ->whereNotNull('borrower_user_id');

        if ($this->contractId !== null) {
            $query->where('id', $this->contractId);
        }

        $contracts = $query->get();

        foreach ($contracts as $contract) {
            $dayOfMonth = (int) ($contract->payment_day_of_month ?? 0);
            if ($dayOfMonth >= 1 && $dayOfMonth <= 28) {
                if ($today->day === $dayOfMonth) {
                    $service->createPendingForDueDate($contract, $today);
                }
            } else {
                if ($contract->due_date && $contract->due_date->isSameDay($today)) {
                    $service->createPendingForDueDate($contract, $today);
                }
            }
        }
    }
}
