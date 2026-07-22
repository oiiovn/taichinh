<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodMaterialStockMovement extends Model
{
    public const TYPE_IN = 'in';

    public const TYPE_OUT = 'out';

    public const TYPE_ADJUST = 'adjust';

    protected $table = 'food_material_stock_movements';

    protected $fillable = [
        'food_material_id',
        'user_id',
        'food_sales_report_id',
        'food_branch_id',
        'type',
        'qty',
        'stock_after',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'stock_after' => 'decimal:4',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(FoodMaterial::class, 'food_material_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function salesReport(): BelongsTo
    {
        return $this->belongsTo(FoodSalesReport::class, 'food_sales_report_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(FoodBranch::class, 'food_branch_id');
    }
}
