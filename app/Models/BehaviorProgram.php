<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BehaviorProgram extends Model
{
    protected $table = 'behavior_programs';

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'start_date',
        'end_date',
        'duration_days',
        'archetype',
        'difficulty_level',
        'validation_strategy',
        'escalation_rule',
        'status',
        'daily_target_count',
        'skip_policy',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'duration_days' => 'integer',
        'daily_target_count' => 'integer',
        'difficulty_level' => 'integer',
    ];

    public const ARCHETYPE_BINARY = 'binary';
    public const ARCHETYPE_QUANTITATIVE = 'quantitative';
    public const ARCHETYPE_AVOIDANCE = 'avoidance';
    public const ARCHETYPE_PROGRESSIVE = 'progressive';
    public const ARCHETYPE_IDENTITY = 'identity';

    public const ARCHETYPES = [
        self::ARCHETYPE_BINARY => 'Có/Không (mỗi ngày 1 lần)',
        self::ARCHETYPE_QUANTITATIVE => 'Định lượng (mỗi ngày N lần)',
        self::ARCHETYPE_AVOIDANCE => 'Tránh (không làm X)',
        self::ARCHETYPE_PROGRESSIVE => 'Tăng dần độ khó',
        self::ARCHETYPE_IDENTITY => 'Gắn với bản sắc',
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_ACTIVE => 'Đang thực hiện',
        self::STATUS_COMPLETED => 'Hoàn thành',
        self::STATUS_FAILED => 'Thất bại',
    ];

    /** Quy tắc khi lỡ 1 ngày: bỏ luôn / cho phép 2 lần bỏ / tự giảm độ khó */
    public const ESCALATION_ABANDON = 'abandon';
    public const ESCALATION_ALLOW_2_SKIPS = 'allow_2_skips';
    public const ESCALATION_REDUCE_DIFFICULTY = 'reduce_difficulty';

    public const ESCALATION_RULES = [
        self::ESCALATION_ABANDON => 'Bỏ luôn (fail ngay)',
        self::ESCALATION_ALLOW_2_SKIPS => 'Cho phép tối đa 2 ngày bỏ',
        self::ESCALATION_REDUCE_DIFFICULTY => 'Tự giảm độ khó khi lỡ',
    ];

    public const SKIP_POLICY_ABANDON = 'abandon';
    public const SKIP_POLICY_ALLOW_2 = 'allow_2_skips';
    public const SKIP_POLICY_REDUCE = 'reduce_difficulty';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CongViecTask::class, 'program_id');
    }

    public function getEndDateResolved(): Carbon
    {
        if ($this->end_date) {
            return Carbon::parse($this->end_date);
        }
        if ($this->duration_days) {
            return Carbon::parse($this->start_date)->addDays((int) $this->duration_days);
        }
        return Carbon::parse($this->start_date)->addDays(30);
    }

    public function getDaysTotal(): int
    {
        return $this->getEndDateResolved()->diffInDays(Carbon::parse($this->start_date)) + 1;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
