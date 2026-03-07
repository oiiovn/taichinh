<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkTaskInstance extends Model
{
    protected $table = 'work_task_instances';

    protected $fillable = [
        'work_task_id',
        'instance_date',
        'status',
        'completed_at',
        'skipped',
        'notes',
    ];

    protected $casts = [
        'instance_date' => 'date',
        'completed_at' => 'datetime',
        'skipped' => 'boolean',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_SKIPPED = 'skipped';

    public function task(): BelongsTo
    {
        return $this->belongsTo(CongViecTask::class, 'work_task_id');
    }

    public function getCompletedAttribute(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
