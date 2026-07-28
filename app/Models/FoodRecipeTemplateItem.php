<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodRecipeTemplateItem extends Model
{
    public const TYPE_MATERIAL = 'material';

    public const TYPE_RECIPE = 'recipe';

    protected $table = 'food_recipe_template_items';

    protected $fillable = [
        'food_recipe_template_id',
        'item_type',
        'food_material_id',
        'child_template_id',
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

    public function childTemplate(): BelongsTo
    {
        return $this->belongsTo(FoodRecipeTemplate::class, 'child_template_id');
    }

    public function isMaterialLine(): bool
    {
        return ($this->item_type ?? self::TYPE_MATERIAL) === self::TYPE_MATERIAL;
    }

    public function isRecipeLine(): bool
    {
        return ($this->item_type ?? self::TYPE_MATERIAL) === self::TYPE_RECIPE;
    }
}
