<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodReportBonusTier extends Model
{
    protected $table = 'food_report_bonus_tiers';

    protected $fillable = [
        'min_total_cost',
        'bonus_amount',
        'sort_order',
    ];

    protected $casts = [
        'min_total_cost' => 'decimal:0',
        'bonus_amount' => 'decimal:0',
    ];

    /**
     * Tính thưởng theo tổng vốn: lấy bậc có min_total_cost <= totalCost cao nhất.
     */
    public static function getBonusForTotalCost(float $totalCost): float
    {
        $tier = self::query()
            ->where('min_total_cost', '<=', (int) round($totalCost))
            ->orderByDesc('min_total_cost')
            ->first();

        return $tier ? (float) $tier->bonus_amount : 0.0;
    }
}
