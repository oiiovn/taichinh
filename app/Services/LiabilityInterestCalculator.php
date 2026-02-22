<?php

namespace App\Services;

use App\Models\UserLiability;
use Carbon\Carbon;

class LiabilityInterestCalculator
{
    /**
     * Lãi theo ngày (rate per day) từ interest_rate + interest_unit.
     */
    public function getDailyRate(UserLiability $liability): float
    {
        $rate = (float) $liability->interest_rate / 100;
        return match ($liability->interest_unit) {
            UserLiability::INTEREST_UNIT_DAILY => $rate,
            UserLiability::INTEREST_UNIT_MONTHLY => $rate / 30,
            UserLiability::INTEREST_UNIT_YEARLY => $rate / 365,
            default => $rate / 365,
        };
    }

    /**
     * Tính lãi cho một số ngày.
     * Simple: interest = principal * dailyRate * days
     * Compound: tính từng ngày và cộng dồn principal (gọi từng kỳ accrual).
     */
    public function calculateInterest(
        float $principal,
        UserLiability $liability,
        int $days
    ): float {
        $dailyRate = $this->getDailyRate($liability);
        if ($liability->interest_calculation === UserLiability::INTEREST_CALCULATION_SIMPLE) {
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

    /**
     * Số ngày cần accrual từ lastAccruedAt đến endDate (tối đa 1 ngày nếu frequency daily).
     */
    public function getDaysToAccrue(
        UserLiability $liability,
        ?Carbon $lastAccruedAt,
        Carbon $asOfDate
    ): int {
        $from = $lastAccruedAt
            ? $lastAccruedAt->copy()->addDay()
            : $liability->start_date->copy();
        if ($from->isAfter($asOfDate)) {
            return 0;
        }
        $days = $from->diffInDays($asOfDate) + 1;
        return match ($liability->accrual_frequency) {
            UserLiability::ACCRUAL_FREQUENCY_DAILY => min(1, $days),
            UserLiability::ACCRUAL_FREQUENCY_WEEKLY => min(7, $days),
            UserLiability::ACCRUAL_FREQUENCY_MONTHLY => min((int) $from->daysInMonth, $days),
            default => min(1, $days),
        };
    }

    /**
     * Trả về interest cho 1 kỳ accrual (1 ngày / 1 tuần / 1 tháng tùy frequency).
     */
    public function interestForOnePeriod(float $principal, UserLiability $liability): float
    {
        $periodDays = match ($liability->accrual_frequency) {
            UserLiability::ACCRUAL_FREQUENCY_DAILY => 1,
            UserLiability::ACCRUAL_FREQUENCY_WEEKLY => 7,
            UserLiability::ACCRUAL_FREQUENCY_MONTHLY => 30,
            default => 1,
        };
        return $this->calculateInterest($principal, $liability, $periodDays);
    }
}
