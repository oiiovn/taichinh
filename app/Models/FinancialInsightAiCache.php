<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialInsightAiCache extends Model
{
    protected $table = 'financial_insight_ai_cache';

    protected $fillable = [
        'user_id',
        'snapshot_hash',
        'narrative',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
