<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BehaviorLog extends Model
{
    protected $table = 'behavior_logs';

    protected $fillable = [
        'user_id',
        'suggestion_type',
        'accepted',
        'action_taken',
        'logged_at',
    ];

    protected $casts = [
        'accepted' => 'boolean',
        'action_taken' => 'boolean',
        'logged_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
