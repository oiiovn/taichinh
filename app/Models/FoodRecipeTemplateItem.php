<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodRecipeTemplateItem extends Model
{
    protected $table = 'food_recipe_template_items';

    protected $fillable = [
        'food_recipe_template_id',
        'food_material_id',
        'qty_per_unit',
    ];

    protected function casts(): array
    {
        return [
            'qty_per_unit' => 'decimal:6',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(FoodRecipeTemplate::class, 'food_recipe_template_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(FoodMaterial::class, 'food_material_id');
    }
}
