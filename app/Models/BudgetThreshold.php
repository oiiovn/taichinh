<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetThreshold extends Model
{
    protected $table = 'budget_thresholds';

    protected $fillable = [
        'user_id',
        'name',
        'amount_limit_vnd',
        'period_type',
        'year',
        'month',
        'period_start',
        'period_end',
        'category_bindings',
        'is_active',
    ];

    protected $casts = [
        'category_bindings' => 'array',
        'amount_limit_vnd' => 'integer',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(BudgetThresholdSnapshot::class);
    }
}
