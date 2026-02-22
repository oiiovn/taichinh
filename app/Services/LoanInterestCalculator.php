<?php

namespace App\Services;

use App\Models\LoanContract;

class LoanInterestCalculator
{
    public function getDailyRate(LoanContract $contract): float
    {
        $rate = (float) $contract->interest_rate / 100;
        return match ($contract->interest_unit) {
            LoanContract::INTEREST_UNIT_DAILY => $rate,
            LoanContract::INTEREST_UNIT_MONTHLY => $rate / 30,
            LoanContract::INTEREST_UNIT_YEARLY => $rate / 365,
            default => $rate / 365,
        };
    }

    public function calculateInterest(float $principal, LoanContract $contract, int $days): float
    {
        $dailyRate = $this->getDailyRate($contract);
        if (in_array($contract->interest_calculation, [LoanContract::INTEREST_CALCULATION_SIMPLE, LoanContract::INTEREST_CALCULATION_REDUCING], true)) {
            return round($principal * $dailyRate * $days, 2);
        }
        $interest = 0.0;
        $p = $principal;
        for ($i = 0; $i < $days; $i++) {
            $dayInterest = $p * $dailyRate;
            $interest += $dayInterest;
            $p += $dayInterest;
        }
        return round($interest, 2);
    }
}
