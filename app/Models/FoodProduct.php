<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FoodProduct extends Model
{
    protected $table = 'food_products';

    protected $fillable = [
        'user_id',
        'ma_hang',
        'ten_hang',
        'gia_von',
        'is_combo',
        'food_recipe_template_id',
    ];

    /** Tiền VND: luôn lưu số nguyên đồng, không lưu dạng dấu chấm phân cách hàng nghìn. */
    protected $casts = [
        'gia_von' => 'integer',
        'is_combo' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(FoodProductRecipe::class, 'food_product_id');
    }

    public function recipeTemplate(): BelongsTo
    {
        return $this->belongsTo(FoodRecipeTemplate::class, 'food_recipe_template_id');
    }
}
