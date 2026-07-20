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
        'apply_late_penalty',
        'shift_start_time',
    ];

    protected $attributes = [
        'salary_type' => 'hour',
        'apply_late_penalty' => false,
    ];

    protected function casts(): array
    {
        return [
            'salary_rate' => 'decimal:2',
            'start_date' => 'date',
            'active' => 'boolean',
            'apply_late_penalty' => 'boolean',
        ];
    }

    public const SALARY_TYPE_HOUR = 'hour';

    public const SALARY_TYPE_DAY = 'day';

    public const SALARY_TYPE_MONTH = 'month';

    /** Phút 1–5: 10.000đ/phút; từ phút 6: 5.000đ/phút */
    public const LATE_PENALTY_FIRST_MINUTES = 5;

    public const LATE_PENALTY_FIRST_RATE = 10000;

    public const LATE_PENALTY_AFTER_RATE = 5000;

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

    public function salaryPayments(): HasMany
    {
        return $this->hasMany(EmployeeSalaryPayment::class)->orderByDesc('paid_at');
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

    public function usesLatePenalty(): bool
    {
        return (bool) $this->apply_late_penalty && filled($this->shift_start_time);
    }

    /** Giờ vào ca chuẩn dạng H:i (vd. 08:00). */
    public function shiftStartTimeHi(): ?string
    {
        if (! filled($this->shift_start_time)) {
            return null;
        }
        $raw = (string) $this->shift_start_time;
        if (preg_match('/^(\d{1,2}):(\d{2})/', $raw, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        try {
            return Carbon::parse($raw)->format('H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    public function lateMinutesForCheckIn(?Carbon $checkInAt): int
    {
        if (! $this->usesLatePenalty() || ! $checkInAt) {
            return 0;
        }
        $startHi = $this->shiftStartTimeHi();
        if (! $startHi) {
            return 0;
        }
        [$sh, $sm] = array_map('intval', explode(':', $startHi));
        $startMins = $sh * 60 + $sm;
        $inMins = $checkInAt->hour * 60 + $checkInAt->minute;

        return max(0, $inMins - $startMins);
    }

    public function latePenaltyForMinutes(int $lateMinutes): int
    {
        if ($lateMinutes <= 0) {
            return 0;
        }
        $first = min($lateMinutes, self::LATE_PENALTY_FIRST_MINUTES);
        $rest = max(0, $lateMinutes - self::LATE_PENALTY_FIRST_MINUTES);

        return ($first * self::LATE_PENALTY_FIRST_RATE) + ($rest * self::LATE_PENALTY_AFTER_RATE);
    }

    public function latePenaltyForLog(AttendanceLog $log): int
    {
        return $this->latePenaltyForMinutes($this->lateMinutesForCheckIn($log->check_in_at));
    }

    public function formatLatePenaltyNote(int $lateMinutes, int $penalty): string
    {
        $amount = number_format($penalty, 0, ',', '.');

        return "Đi trễ {$lateMinutes} phút — phạt {$amount}đ";
    }

    /** Gỡ đoạn ghi chú phạt tự động (nếu có) để cập nhật lại cho đúng. */
    public function stripLatePenaltyNote(?string $note): string
    {
        if ($note === null || trim($note) === '') {
            return '';
        }
        $cleaned = preg_replace('/\s*\|\s*Đi trễ \d+ phút — phạt [\d.]+đ/u', '', $note);
        $cleaned = preg_replace('/^Đi trễ \d+ phút — phạt [\d.]+đ(\s*\|\s*)?/u', '', $cleaned ?? '');

        return trim((string) $cleaned);
    }

    /**
     * Ghép ghi chú phạt đi trễ vào note của ngày.
     * Không trễ → bỏ đoạn phạt tự động, giữ phần ghi chú tay.
     */
    public function mergeLatePenaltyIntoNote(?string $existingNote, ?Carbon $checkInAt): ?string
    {
        $base = $this->stripLatePenaltyNote($existingNote);
        if (! $this->usesLatePenalty() || ! $checkInAt) {
            return $base !== '' ? $base : null;
        }

        $mins = $this->lateMinutesForCheckIn($checkInAt);
        $penalty = $this->latePenaltyForMinutes($mins);
        if ($mins <= 0 || $penalty <= 0) {
            return $base !== '' ? $base : null;
        }

        $auto = $this->formatLatePenaltyNote($mins, $penalty);
        $merged = $base !== '' ? $base.' | '.$auto : $auto;

        return mb_substr($merged, 0, 500);
    }

    public function applyLatePenaltyNote(AttendanceLog $log): void
    {
        $log->note = $this->mergeLatePenaltyIntoNote($log->note, $log->check_in_at);
        $log->save();
    }
}
