<?php

namespace App\Jobs;

use App\Models\LiabilityAccrual;
use App\Models\UserLiability;
use App\Services\LiabilityInterestCalculator;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class AccrueLiabilityInterestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?int $userId = null
    ) {}

    public function handle(LiabilityInterestCalculator $calculator): void
    {
        $asOf = Carbon::today();

        $query = UserLiability::query()
            ->where('status', UserLiability::STATUS_ACTIVE)
            ->where('auto_accrue', true)
            ->with('accruals');

        if ($this->userId !== null) {
            $query->where('user_id', $this->userId);
        }

        $liabilities = $query->get();

        foreach ($liabilities as $liability) {
            if ($liability->start_date->isAfter($asOf)) {
                continue;
            }

            $lastAccrued = $liability->accruals()->orderBy('accrued_at', 'desc')->first();
            $from = $lastAccrued
                ? $lastAccrued->accrued_at->copy()->addDay()
                : $liability->start_date->copy();

            $periodDays = match ($liability->accrual_frequency) {
                UserLiability::ACCRUAL_FREQUENCY_DAILY => 1,
                UserLiability::ACCRUAL_FREQUENCY_WEEKLY => 7,
                UserLiability::ACCRUAL_FREQUENCY_MONTHLY => 30,
                default => 1,
            };

            while ($from->lte($asOf)) {
                $periodEnd = $from->copy()->addDays($periodDays - 1);
                if ($periodEnd->gt($asOf)) {
                    break;
                }
                $principal = (float) $liability->principal;
                $interest = $calculator->calculateInterest($principal, $liability, $periodDays);

                if ($interest <= 0) {
                    $from->addDays($periodDays);
                    continue;
                }

                DB::transaction(function () use ($liability, $interest, $from, $periodDays) {
                    LiabilityAccrual::create([
                        'liability_id' => $liability->id,
                        'amount' => $interest,
                        'accrued_at' => $from->copy(),
                        'source' => LiabilityAccrual::SOURCE_SYSTEM,
                    ]);
                    if ($liability->interest_calculation === UserLiability::INTEREST_CALCULATION_COMPOUND) {
                        $liability->principal = (float) $liability->principal + $interest;
                        $liability->save();
                    }
                });

                $liability->refresh();
                $from->addDays($periodDays);
            }
        }
    }
}
