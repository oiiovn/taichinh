<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardEventState extends Model
{
    protected $table = 'dashboard_event_states';

    protected $fillable = [
        'user_id',
        'event_type',
        'event_key',
        'status',
        'acknowledged_at',
        'resolved_at',
    ];

    protected $casts = [
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_RESOLVED = 'resolved';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
