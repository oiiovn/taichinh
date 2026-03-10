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
        'actual_duration',
        'focus_started_at',
        'focus_last_activity_at',
        'focus_stopped_at',
        'focus_recorded_minutes',
        'ghost_completion_detected',
        'ghost_completion_confirmed',
        'skipped',
        'notes',
    ];

    protected $casts = [
        'instance_date' => 'date',
        'completed_at' => 'datetime',
        'focus_started_at' => 'datetime',
        'focus_last_activity_at' => 'datetime',
        'focus_stopped_at' => 'datetime',
        'skipped' => 'boolean',
        'ghost_completion_detected' => 'boolean',
        'ghost_completion_confirmed' => 'boolean',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_SKIPPED = 'skipped';

    public function task(): BelongsTo
    {
        return $this->belongsTo(CongViecTask::class, 'work_task_id');
    }

    /**
     * Instance của user (task thuộc user_id). Dùng forLearning=true khi cần học từ lịch sử cả task đã xóa.
     */
    public function scopeWhereUser(\Illuminate\Database\Eloquent\Builder $query, int $userId, bool $forLearning = false): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereHas('task', function ($q) use ($userId, $forLearning) {
            $q->where('user_id', $userId);
            if ($forLearning) {
                $q->withTrashed();
            }
        });
    }

    public function getCompletedAttribute(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
