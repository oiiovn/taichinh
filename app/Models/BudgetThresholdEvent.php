<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetThresholdEvent extends Model
{
    public $timestamps = false;

    protected $table = 'budget_threshold_events';

    protected $fillable = [
        'user_id',
        'budget_threshold_id',
        'event_type',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function budgetThreshold(): BelongsTo
    {
        return $this->belongsTo(BudgetThreshold::class);
    }
}
