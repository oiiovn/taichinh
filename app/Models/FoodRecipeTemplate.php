<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FoodRecipeTemplate extends Model
{
    protected $table = 'food_recipe_templates';

    protected $fillable = [
        'user_id',
        'name',
        'note',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FoodRecipeTemplateItem::class, 'food_recipe_template_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(FoodProduct::class, 'food_recipe_template_id');
    }
}
