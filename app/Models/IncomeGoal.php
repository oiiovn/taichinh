<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncomeGoal extends Model
{
    protected $table = 'income_goals';

    protected $fillable = [
        'user_id',
        'name',
        'amount_target_vnd',
        'period_type',
        'year',
        'month',
        'period_start',
        'period_end',
        'category_bindings',
        'expense_category_bindings',
        'income_source_keywords',
        'is_active',
    ];

    protected $casts = [
        'category_bindings' => 'array',
        'expense_category_bindings' => 'array',
        'income_source_keywords' => 'array',
        'amount_target_vnd' => 'integer',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(IncomeGoalSnapshot::class);
    }

    public function userIncomeSources(): HasMany
    {
        return $this->hasMany(UserIncomeSource::class, 'income_goal_id');
    }
}
