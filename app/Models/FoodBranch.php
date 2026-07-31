<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FoodBranch extends Model
{
    protected $table = 'food_branches';

    protected $fillable = [
        'user_id',
        'name',
        'address',
        'branch_link',
        'latitude',
        'longitude',
        'check_in_radius_meters',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'check_in_radius_meters' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_food_branch')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function salesReports(): HasMany
    {
        return $this->hasMany(FoodSalesReport::class, 'food_branch_id');
    }

    public function materialStocks(): HasMany
    {
        return $this->hasMany(FoodMaterialStock::class, 'food_branch_id');
    }
}
