<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodProductRecipe extends Model
{
    protected $table = 'food_product_recipes';

    protected $fillable = [
        'food_product_id',
        'food_material_id',
        'qty_per_unit',
    ];

    protected function casts(): array
    {
        return [
            'qty_per_unit' => 'decimal:6',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(FoodProduct::class, 'food_product_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(FoodMaterial::class, 'food_material_id');
    }
}
