<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncomeGoalEvent extends Model
{
    public $timestamps = false;

    protected $table = 'income_goal_events';

    protected $fillable = [
        'user_id',
        'income_goal_id',
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

    public function incomeGoal(): BelongsTo
    {
        return $this->belongsTo(IncomeGoal::class);
    }
}
