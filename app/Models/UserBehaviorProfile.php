<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBehaviorProfile extends Model
{
    protected $table = 'user_behavior_profiles';

    protected $fillable = [
        'user_id',
        'debt_style',
        'risk_tolerance',
        'spending_discipline_score',
        'execution_consistency_score',
        'execution_consistency_score_reduce_expense',
        'execution_consistency_score_debt',
        'execution_consistency_score_income',
        'surplus_usage_pattern',
        'volatility_reaction_pattern',
        'risk_underestimation_flag',
    ];

    protected $casts = [
        'spending_discipline_score' => 'decimal:2',
        'execution_consistency_score' => 'decimal:2',
        'execution_consistency_score_reduce_expense' => 'decimal:2',
        'execution_consistency_score_debt' => 'decimal:2',
        'execution_consistency_score_income' => 'decimal:2',
    ];

    public const DEBT_STYLE_SNOWBALL = 'snowball';
    public const DEBT_STYLE_AVALANCHE = 'avalanche';
    public const DEBT_STYLE_MIXED = 'mixed';
    public const DEBT_STYLE_UNKNOWN = 'unknown';

    public const RISK_LOW = 'low';
    public const RISK_MEDIUM = 'medium';
    public const RISK_HIGH = 'high';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
