<?php

namespace App\Services;

use App\Models\EstimatedExpense;
use App\Models\EstimatedIncome;
use App\Models\IncomeSource;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EstimatedAggregateService
{
    /**
     * Block 1 – Hôm nay: thu, chi, chênh lệch.
     */
    public function todaySummary(int $userId): array
    {
        $today = Carbon::today();
        $thu = (float) EstimatedIncome::query()
            ->where('user_id', $userId)
            ->whereDate('date', $today)
            ->sum('amount');
        $chi = (float) EstimatedExpense::query()
            ->where('user_id', $userId)
            ->whereDate('date', $today)
            ->sum('amount');

        return [
            'thu' => $thu,
            'chi' => $chi,
            'net' => $thu - $chi,
        ];
    }

    /**
     * Block 2 – 7 ngày gần nhất: mỗi ngày có thu theo nguồn, tổng thu, chi, net.
     */
    public function last7Days(int $userId): array
    {
        $today = Carbon::today();
        $dates = collect();
        for ($i = 6; $i >= 0; $i--) {
            $dates->push($today->copy()->subDays($i));
        }

        $incomes = EstimatedIncome::query()
            ->where('user_id', $userId)
            ->whereBetween('date', [$dates->first(), $dates->last()])
            ->with('source:id,name,color')
            ->get()
            ->groupBy(fn ($e) => $e->date->format('Y-m-d'));

        $expenses = EstimatedExpense::query()
            ->where('user_id', $userId)
            ->whereBetween('date', [$dates->first(), $dates->last()])
            ->get()
            ->groupBy(fn ($e) => $e->date->format('Y-m-d'));

        $sources = IncomeSource::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        $result = [];
        foreach ($dates as $d) {
            $key = $d->format('Y-m-d');
            $dayIncomes = $incomes->get($key, collect());
            $bySource = $dayIncomes->groupBy('source_id')->map(function ($items, $sourceId) {
                $first = $items->first();
                return [
                    'name' => $first->source?->name ?? '—',
                    'color' => $first->source?->color,
                    'amount' => (float) $items->sum('amount'),
                ];
            })->values()->all();
            $thuDay = (float) $dayIncomes->sum('amount');
            $chiDay = (float) $expenses->get($key, collect())->sum('amount');

            $result[] = [
                'date' => $d->format('Y-m-d'),
                'date_label' => $d->format('d/m'),
                'by_source' => $bySource,
                'thu' => $thuDay,
                'chi' => $chiDay,
                'net' => $thuDay - $chiDay,
            ];
        }

        return $result;
    }

    /**
     * Block 3 – Theo nguồn thu: mỗi nguồn có hôm nay, tuần này, tháng này.
     */
    public function bySource(int $userId): array
    {
        $today = Carbon::today();
        $startWeek = $today->copy()->startOfWeek();
        $startMonth = $today->copy()->startOfMonth();

        $sources = IncomeSource::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $incomes = EstimatedIncome::query()
            ->where('user_id', $userId)
            ->whereBetween('date', [$startMonth, $today])
            ->selectRaw('source_id, date, amount')
            ->get()
            ->groupBy('source_id');

        $result = [];
        foreach ($sources as $source) {
            $items = $incomes->get($source->id, collect());
            $todayAmount = (float) $items->filter(fn ($i) => $i->date->isSameDay($today))->sum('amount');
            $weekAmount = (float) $items->filter(fn ($i) => $i->date->gte($startWeek))->sum('amount');
            $monthAmount = (float) $items->sum('amount');

            $result[] = [
                'id' => $source->id,
                'name' => $source->name,
                'color' => $source->color,
                'today' => $todayAmount,
                'week' => $weekAmount,
                'month' => $monthAmount,
            ];
        }

        return $result;
    }

    /**
     * Dự kiến tháng này: trung bình thu/chi mỗi ngày (từ đầu tháng đến hôm nay) × số ngày trong tháng.
     */
    public function monthProjection(int $userId): array
    {
        $today = Carbon::today();
        $startMonth = $today->copy()->startOfMonth();
        $daysInMonth = (int) $today->copy()->endOfMonth()->day;
        $daysElapsed = $today->day; // 1..31

        $thuSoFar = (float) EstimatedIncome::query()
            ->where('user_id', $userId)
            ->whereBetween('date', [$startMonth, $today])
            ->sum('amount');
        $chiSoFar = (float) EstimatedExpense::query()
            ->where('user_id', $userId)
            ->whereBetween('date', [$startMonth, $today])
            ->sum('amount');

        $avgThuPerDay = $daysElapsed > 0 ? $thuSoFar / $daysElapsed : 0.0;
        $avgChiPerDay = $daysElapsed > 0 ? $chiSoFar / $daysElapsed : 0.0;

        $thuProjected = round($avgThuPerDay * $daysInMonth, 0);
        $chiProjected = round($avgChiPerDay * $daysInMonth, 0);

        return [
            'thu' => $thuProjected,
            'chi' => $chiProjected,
            'net' => $thuProjected - $chiProjected,
            'days_elapsed' => $daysElapsed,
            'days_in_month' => $daysInMonth,
            'thu_so_far' => $thuSoFar,
            'chi_so_far' => $chiSoFar,
        ];
    }
}
