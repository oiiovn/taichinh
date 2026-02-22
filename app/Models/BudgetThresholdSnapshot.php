<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetThresholdSnapshot extends Model
{
    protected $table = 'budget_threshold_snapshots';

    protected $fillable = [
        'budget_threshold_id',
        'period_key',
        'period_start',
        'period_end',
        'amount_limit_vnd',
        'total_spent_vnd',
        'deviation_pct',
        'breached',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'amount_limit_vnd' => 'integer',
        'total_spent_vnd' => 'integer',
        'deviation_pct' => 'float',
        'breached' => 'boolean',
    ];

    public function budgetThreshold(): BelongsTo
    {
        return $this->belongsTo(BudgetThreshold::class);
    }
}
