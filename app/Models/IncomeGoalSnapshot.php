<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncomeGoalSnapshot extends Model
{
    protected $table = 'income_goal_snapshots';

    protected $fillable = [
        'income_goal_id',
        'period_key',
        'period_start',
        'period_end',
        'amount_target_vnd',
        'total_earned_vnd',
        'achievement_pct',
        'met',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'amount_target_vnd' => 'integer',
        'total_earned_vnd' => 'integer',
        'achievement_pct' => 'float',
        'met' => 'boolean',
    ];

    public function incomeGoal(): BelongsTo
    {
        return $this->belongsTo(IncomeGoal::class);
    }
}
