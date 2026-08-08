<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class FoodAttendanceSaleDay extends Model
{
    protected $table = 'food_attendance_sale_days';

    protected $fillable = [
        'work_date',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
        ];
    }

    public static function isSaleDay(Carbon|string|null $workDate): bool
    {
        if ($workDate === null || $workDate === '') {
            return false;
        }

        $date = Carbon::parse($workDate)->toDateString();

        return static::query()->whereDate('work_date', $date)->exists();
    }

    /**
     * @return list<string> Y-m-d
     */
    public static function datesBetween(Carbon $from, Carbon $to): array
    {
        return static::query()
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('work_date')
            ->pluck('work_date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->values()
            ->all();
    }
}
