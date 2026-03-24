<?php

namespace App\Models;

use Carbon\Carbon;
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

    public function salaryRates(): HasMany
    {
        return $this->hasMany(EmployeeSalaryRate::class)->orderBy('effective_from');
    }

    /**
     * Mức lương + hình thức áp dụng tại một ngày (theo lịch sử lương).
     *
     * @return array{rate: float, type: string}
     */
    public function applicableRateForDate(Carbon $date): array
    {
        $d = $date->copy()->startOfDay();
        if ($this->relationLoaded('salaryRates') && $this->salaryRates->isNotEmpty()) {
            $chosen = $this->salaryRates
                ->filter(fn (EmployeeSalaryRate $r) => $r->effective_from->lte($d))
                ->sortByDesc(fn (EmployeeSalaryRate $r) => $r->effective_from->toDateString())
                ->first();
        } else {
            $chosen = $this->salaryRates()
                ->where('effective_from', '<=', $d)
                ->orderByDesc('effective_from')
                ->first();
        }

        if ($chosen) {
            return ['rate' => (float) $chosen->salary_rate, 'type' => $chosen->salary_type];
        }

        if (! $this->salaryRates()->exists()) {
            return ['rate' => (float) $this->salary_rate, 'type' => $this->salary_type];
        }

        $first = $this->relationLoaded('salaryRates') && $this->salaryRates->isNotEmpty()
            ? $this->salaryRates->sortBy('effective_from')->first()
            : $this->salaryRates()->orderBy('effective_from')->first();

        if ($first && $d->lt($first->effective_from)) {
            return ['rate' => (float) $first->salary_rate, 'type' => $first->salary_type];
        }

        return ['rate' => (float) $this->salary_rate, 'type' => $this->salary_type];
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
