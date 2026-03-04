<?php

namespace App\Services\TaiChinh;

use App\Http\Controllers\Food\FoodController;
use App\Models\TransactionHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TongquanStatisticService
{
    public static function getDateRangeFromPeriod(string $period): array
    {
        $now = Carbon::now();
        $to = $now->copy()->endOfDay();
        switch ($period) {
            case FoodController::PERIOD_DAY:
                $from = $now->copy()->startOfDay();
                break;
            case FoodController::PERIOD_WEEK:
                $from = $now->copy()->startOfWeek()->startOfDay();
                break;
            case FoodController::PERIOD_MONTH:
                $from = $now->copy()->startOfMonth()->startOfDay();
                break;
            case FoodController::PERIOD_3MONTH:
                $from = $now->copy()->subMonths(2)->startOfMonth()->startOfDay();
                break;
            case FoodController::PERIOD_6MONTH:
                $from = $now->copy()->subMonths(5)->startOfMonth()->startOfDay();
                break;
            case FoodController::PERIOD_12MONTH:
                $from = $now->copy()->subMonths(11)->startOfMonth()->startOfDay();
                break;
            default:
                $from = $now->copy()->startOfMonth()->startOfDay();
        }
        return [$from, $to];
    }

    public static function parseDate(?string $value): ?Carbon
    {
        if (! $value || ! is_string($value)) {
            return null;
        }
        $value = trim($value);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                $c = Carbon::createFromFormat($format, $value);
                if ($c instanceof Carbon) {
                    return $c;
                }
            } catch (\Throwable $e) {
            }
        }
        return null;
    }

    /**
     * @param  array<int>  $thuCategoryIds
     * @param  array<int>  $chiCategoryIds
     * @param  array<string>  $linkedAccountNumbers
     * @return array{thuTotal: float, chiTotal: float, loiNhuan: float, chartDates: array<string>, chartThu: array<int>, chartChi: array<int>, chartLoiNhuan: array<int>}
     */
    public function compute(
        int $userId,
        Carbon $from,
        Carbon $to,
        array $thuCategoryIds,
        array $chiCategoryIds,
        array $linkedAccountNumbers = []
    ): array {
        $baseQuery = TransactionHistory::query()
            ->where('user_id', $userId)
            ->whereBetween('transaction_date', [$from, $to]);

        if (! empty($linkedAccountNumbers)) {
            $baseQuery->whereIn('account_number', $linkedAccountNumbers);
        }

        $thuTotal = 0.0;
        $chiTotal = 0.0;
        if (! empty($thuCategoryIds)) {
            $thuTotal = (float) (clone $baseQuery)->where('type', 'IN')->whereIn('user_category_id', $thuCategoryIds)->sum('amount');
        }
        if (! empty($chiCategoryIds)) {
            $chiTotal = (float) (clone $baseQuery)->where('type', 'OUT')->whereIn('user_category_id', $chiCategoryIds)->sum(DB::raw('ABS(amount)'));
        }

        $chartDates = [];
        $chartThu = [];
        $chartChi = [];
        $chartLoiNhuan = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $dayStart = $cursor->copy()->startOfDay();
            $dayEnd = $cursor->copy()->endOfDay();
            $chartDates[] = $cursor->format('d/m');
            $dayThu = 0.0;
            $dayChi = 0.0;
            if (! empty($thuCategoryIds)) {
                $q = TransactionHistory::query()
                    ->where('user_id', $userId)
                    ->where('type', 'IN')
                    ->whereIn('user_category_id', $thuCategoryIds)
                    ->whereBetween('transaction_date', [$dayStart, $dayEnd]);
                if (! empty($linkedAccountNumbers)) {
                    $q->whereIn('account_number', $linkedAccountNumbers);
                }
                $dayThu = (float) $q->sum('amount');
            }
            if (! empty($chiCategoryIds)) {
                $q = TransactionHistory::query()
                    ->where('user_id', $userId)
                    ->where('type', 'OUT')
                    ->whereIn('user_category_id', $chiCategoryIds)
                    ->whereBetween('transaction_date', [$dayStart, $dayEnd]);
                if (! empty($linkedAccountNumbers)) {
                    $q->whereIn('account_number', $linkedAccountNumbers);
                }
                $dayChi = (float) $q->sum(DB::raw('ABS(amount)'));
            }
            $chartThu[] = (int) round($dayThu);
            $chartChi[] = (int) round($dayChi);
            $chartLoiNhuan[] = (int) round($dayThu - $dayChi);
            $cursor->addDay();
        }

        return [
            'thuTotal' => $thuTotal,
            'chiTotal' => $chiTotal,
            'loiNhuan' => $thuTotal - $chiTotal,
            'chartDates' => $chartDates,
            'chartThu' => $chartThu,
            'chartChi' => $chartChi,
            'chartLoiNhuan' => $chartLoiNhuan,
        ];
    }
}
