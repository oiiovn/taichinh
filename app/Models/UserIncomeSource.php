<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserIncomeSource extends Model
{
    protected $table = 'user_income_sources';

    protected $fillable = [
        'user_id',
        'name',
        'source_type',
        'detection_mode',
        'stability_score',
        'volatility_score',
        'is_active',
        'created_from',
        'income_goal_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'stability_score' => 'float',
        'volatility_score' => 'float',
    ];

    public const SOURCE_TYPE_BUSINESS = 'business';
    public const SOURCE_TYPE_SALARY = 'salary';
    public const SOURCE_TYPE_FREELANCE = 'freelance';
    public const SOURCE_TYPE_RENTAL = 'rental';
    public const SOURCE_TYPE_OTHER = 'other';

    public const CREATED_FROM_GOAL = 'goal';
    public const CREATED_FROM_MANUAL = 'manual';
    public const CREATED_FROM_LEARNED = 'learned';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function incomeGoal(): BelongsTo
    {
        return $this->belongsTo(IncomeGoal::class, 'income_goal_id');
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(IncomeSourceKeyword::class, 'income_source_id');
    }
}
