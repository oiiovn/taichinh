<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = [
        'user_id',
        'position',
        'salary_type',
        'salary_rate',
        'start_date',
        'active',
    ];

    protected $attributes = [
        'salary_type' => 'hour',
    ];

    protected function casts(): array
    {
        return [
            'salary_rate' => 'decimal:2',
            'start_date' => 'date',
            'active' => 'boolean',
        ];
    }

    public const SALARY_TYPE_HOUR = 'hour';
    public const SALARY_TYPE_DAY = 'day';
    public const SALARY_TYPE_MONTH = 'month';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class)->orderBy('work_date', 'desc');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class)->orderBy('created_at', 'desc');
    }

    public function salaryAdvances(): HasMany
    {
        return $this->hasMany(SalaryAdvance::class)->orderBy('created_at', 'desc');
    }

    public static function salaryTypeLabels(): array
    {
        return [
            self::SALARY_TYPE_HOUR => 'Theo giờ',
            self::SALARY_TYPE_DAY => 'Theo ngày',
            self::SALARY_TYPE_MONTH => 'Theo tháng',
        ];
    }
}
