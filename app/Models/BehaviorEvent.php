<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BehaviorEvent extends Model
{
    public $timestamps = false;

    protected $table = 'behavior_events';

    protected $fillable = [
        'user_id',
        'event_type',
        'work_task_id',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public const TYPE_APP_OPEN_AFTER_REMINDER = 'app_open_after_reminder';
    public const TYPE_SCROLL_COUNT = 'scroll_count';
    public const TYPE_DWELL_MS = 'dwell_ms';
    public const TYPE_TASK_TICK_AT = 'task_tick_at';
    public const TYPE_POST_TICK_ACTION = 'post_tick_action';
    public const TYPE_REMINDER_READ_AT = 'reminder_read_at';
    public const TYPE_PAGE_VIEW = 'page_view';
    public const TYPE_POLICY_FEEDBACK = 'policy_feedback';

    public static function allowedEventTypes(): array
    {
        return [
            self::TYPE_APP_OPEN_AFTER_REMINDER,
            self::TYPE_SCROLL_COUNT,
            self::TYPE_DWELL_MS,
            self::TYPE_TASK_TICK_AT,
            self::TYPE_POST_TICK_ACTION,
            self::TYPE_REMINDER_READ_AT,
            self::TYPE_PAGE_VIEW,
            self::TYPE_POLICY_FEEDBACK,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workTask(): BelongsTo
    {
        return $this->belongsTo(CongViecTask::class, 'work_task_id');
    }
}
