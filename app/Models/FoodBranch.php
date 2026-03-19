<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FoodBranch extends Model
{
    protected $table = 'food_branches';

    protected $fillable = [
        'user_id',
        'name',
        'address',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function salesReports(): HasMany
    {
        return $this->hasMany(FoodSalesReport::class, 'food_branch_id');
    }
}
