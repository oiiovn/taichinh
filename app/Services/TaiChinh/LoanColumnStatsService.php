<?php

namespace App\Services\TaiChinh;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class LoanColumnStatsService
{
    public const NEAREST_DUE_WINDOW_DAYS = 90;

    public function build(Collection $items, int $windowDays = self::NEAREST_DUE_WINDOW_DAYS): array
    {
        $totalPrincipal = $items->sum('outstanding');
        $totalUnpaid = $items->sum('unpaid_interest');
        $nearestDue = $this->getNearestDueInWindow($items, $windowDays);
        $withRate = $items->filter(fn ($i) => isset($i->entity->interest_rate));
        $avgRate = $withRate->isEmpty() ? 0 : $withRate->avg(fn ($i) => LoanItemRateHelper::annualRate($i->entity));

        $dueDate = $nearestDue?->due_date;
        $today = Carbon::today();
        $nearestDueDays = null;
        if ($dueDate !== null) {
            $d = $dueDate instanceof Carbon ? $dueDate : Carbon::parse($dueDate)->startOfDay();
            $nearestDueDays = $today->diffInDays($d, false);
        }

        return [
            'total_principal' => $totalPrincipal,
            'total_unpaid_interest' => $totalUnpaid,
            'nearest_due' => $dueDate,
            'nearest_due_name' => $nearestDue?->name ?? null,
            'nearest_due_days' => $nearestDueDays,
            'avg_interest_rate_year' => round($avgRate, 1),
        ];
    }

    public function getNearestDueInWindow(Collection $items, int $windowDays): ?object
    {
        $today = Carbon::today();
        $windowEnd = $today->copy()->addDays($windowDays);
        $inWindow = $items->filter(function ($i) use ($today, $windowEnd) {
            if ($i->due_date === null) {
                return false;
            }
            $d = $i->due_date instanceof Carbon ? $i->due_date : Carbon::parse($i->due_date)->startOfDay();
            return $d->gte($today) && $d->lte($windowEnd);
        });
        return $inWindow->sortBy(function ($i) {
            $d = $i->due_date instanceof Carbon ? $i->due_date : Carbon::parse($i->due_date)->startOfDay();
            return $d->format('Y-m-d');
        })->first();
    }
}
