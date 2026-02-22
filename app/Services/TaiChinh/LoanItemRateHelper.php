<?php

namespace App\Services\TaiChinh;

class LoanItemRateHelper
{
    public static function annualRate(object $entity): float
    {
        $rate = (float) ($entity->interest_rate ?? 0);
        $unit = $entity->interest_unit ?? 'yearly';
        return match ($unit) {
            'yearly' => $rate,
            'monthly' => $rate * 12,
            'daily' => $rate * 365,
            default => $rate,
        };
    }
}
