<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CongViecTask extends Model
{
    protected $table = 'work_tasks';

    protected $fillable = [
        'user_id',
        'project_id',
        'title',
        'description_html',
        'priority',
        'due_date',
        'due_time',
        'remind_minutes_before',
        'location',
        'repeat',
        'repeat_until',
        'completed',
        'kanban_status',
        'category',
        'estimated_duration',
        'actual_duration',
        'impact',
        'internalized_at',
        'program_id',
    ];

    public const KANBAN_STATUSES = [
        'backlog' => 'Backlog',
        'this_cycle' => 'This Cycle',
        'in_progress' => 'In Progress',
        'done' => 'Done',
    ];

    public const CATEGORIES = [
        'revenue' => 'Revenue',
        'growth' => 'Growth',
        'maintenance' => 'Maintenance',
    ];

    public const IMPACTS = [
        'high' => 'Cao',
        'medium' => 'TB',
        'low' => 'Thấp',
    ];

    public const REMIND_OPTIONS = [
        null => 'Không nhắc',
        0 => 'Đúng giờ',
        5 => '5 phút trước',
        15 => '15 phút trước',
        30 => '30 phút trước',
        60 => '1 giờ trước',
        120 => '2 giờ trước',
        1440 => '1 ngày trước',
    ];

    protected $casts = [
        'due_date' => 'date',
        'repeat_until' => 'date',
        'completed' => 'boolean',
        'internalized_at' => 'datetime',
    ];

    public const PRIORITY_LABELS = [
        1 => 'Khẩn cấp',
        2 => 'Cao',
        3 => 'Trung bình',
        4 => 'Thấp',
    ];

    public const REPEAT_LABELS = [
        'none' => 'Không lặp',
        'daily' => 'Hàng ngày',
        'weekly' => 'Hàng tuần',
        'monthly' => 'Hàng tháng',
        'custom' => 'Tùy chỉnh',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(BehaviorProgram::class, 'program_id');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'work_task_label', 'work_task_id', 'label_id');
    }

    public function getPriorityLabelAttribute(): ?string
    {
        return $this->priority ? (self::PRIORITY_LABELS[$this->priority] ?? null) : null;
    }

    public function getRepeatLabelAttribute(): ?string
    {
        return self::REPEAT_LABELS[$this->repeat ?? 'none'] ?? null;
    }

    public function getRemindLabelAttribute(): ?string
    {
        return self::REMIND_OPTIONS[$this->remind_minutes_before] ?? null;
    }

    public function getCategoryLabelAttribute(): ?string
    {
        return $this->category ? (self::CATEGORIES[$this->category] ?? null) : null;
    }

    public function getImpactLabelAttribute(): ?string
    {
        return $this->impact ? (self::IMPACTS[$this->impact] ?? null) : null;
    }

    /** Overtime: due_date đã qua mà chưa done. */
    public function getIsOvertimeAttribute(): bool
    {
        if (! $this->due_date || $this->kanban_status === 'done') {
            return false;
        }
        return $this->due_date->lt(Carbon::today());
    }

    /**
     * Kiểm tra công việc có rơi vào ngày $date (theo múi giờ HCM) hay không.
     */
    public function occursOn($date): bool
    {
        $date = Carbon::parse($date)->startOfDay();
        if (! $this->due_date) {
            return false;
        }
        $due = Carbon::parse($this->due_date)->startOfDay();
        if ($date->lt($due)) {
            return false;
        }
        if ($this->repeat_until && $date->gt(Carbon::parse($this->repeat_until)->startOfDay())) {
            return false;
        }
        switch ($this->repeat ?? 'none') {
            case 'none':
                return $date->isSameDay($due);
            case 'daily':
                return true;
            case 'weekly':
                return $date->dayOfWeek === $due->dayOfWeek;
            case 'monthly':
                return $date->day === $due->day;
            case 'custom':
            default:
                return $date->isSameDay($due);
        }
    }

    /**
     * Lấy danh sách công việc xuất hiện vào ngày $date (đã tính lặp: không lặp / ngày / tuần / tháng).
     */
    public static function occurringOnDate($date): \Illuminate\Support\Collection
    {
        $d = Carbon::parse($date)->format('Y-m-d');
        return static::query()
            ->where('user_id', auth()->id())
            ->whereNotNull('due_date')
            ->where('due_date', '<=', $d)
            ->where(function ($q) use ($d) {
                $q->whereNull('repeat_until')->orWhere('repeat_until', '>=', $d);
            })
            ->get()
            ->filter(fn (CongViecTask $task) => $task->occursOn($date));
    }
}
