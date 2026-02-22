<?php

namespace App\Jobs;

use App\Models\LoanContract;
use App\Services\LoanInterestCalculator;
use App\Services\LoanLedgerService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AccrueLoanInterestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?int $contractId = null,
        public ?string $asOfDate = null,
        public bool $persist = true
    ) {}

    public function handle(LoanLedgerService $ledgerService, LoanInterestCalculator $calculator): void
    {
        $asOf = $this->asOfDate ? Carbon::parse($this->asOfDate)->startOfDay() : Carbon::today();
        $query = LoanContract::query()
            ->where('status', LoanContract::STATUS_ACTIVE)
            ->where('auto_accrue', true)
            ->where('start_date', '<=', $asOf);

        if ($this->contractId !== null) {
            $query->where('id', $this->contractId);
        }

        $contracts = $query->get();

        foreach ($contracts as $contract) {
            $lastAccrued = $ledgerService->getLastAccrualDateAsOf($contract, $asOf);
            $from = $lastAccrued
                ? $lastAccrued->copy()->addDay()
                : $contract->start_date->copy();

            $periodDays = match ($contract->accrual_frequency) {
                LoanContract::ACCRUAL_FREQUENCY_DAILY => 1,
                LoanContract::ACCRUAL_FREQUENCY_WEEKLY => 7,
                LoanContract::ACCRUAL_FREQUENCY_MONTHLY => 30,
                default => 1,
            };

            while ($from->lte($asOf)) {
                $periodEnd = $from->copy()->addDays($periodDays - 1);
                if ($periodEnd->gt($asOf)) {
                    break;
                }
                $principal = $ledgerService->getOutstandingPrincipalAsOf($contract, $from->copy()->subDay());
                $interest = $calculator->calculateInterest($principal, $contract, $periodDays);

                if ($interest > 0 && $this->persist) {
                    $ledgerService->addAccrual($contract, $interest, $from->copy());
                }

                $from->addDays($periodDays);
            }
        }
    }
}
