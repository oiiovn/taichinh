<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    /**
     * Từ ngày này trở đi: giờ công tính tiền chỉ tính từ PAID_WORK_START_TIME.
     * Check-in sớm vẫn lưu giờ thật; phút trước mốc không tính lương.
     */
    public const PAID_WORK_START_EFFECTIVE_FROM = '2026-07-20';

    public const PAID_WORK_START_TIME = '11:30';

    protected $fillable = [
        'employee_id',
        'food_branch_id',
        'work_date',
        'check_in_at',
        'check_out_at',
        'break_start_at',
        'break_end_at',
        'note',
        'check_in_latitude',
        'check_in_longitude',
        'check_out_latitude',
        'check_out_longitude',
        'check_in_method',
        'check_out_method',
        'check_in_distance_meters',
        'check_out_distance_meters',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'break_start_at' => 'datetime',
            'break_end_at' => 'datetime',
            'check_in_latitude' => 'decimal:7',
            'check_in_longitude' => 'decimal:7',
            'check_out_latitude' => 'decimal:7',
            'check_out_longitude' => 'decimal:7',
            'check_in_distance_meters' => 'integer',
            'check_out_distance_meters' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function foodBranch(): BelongsTo
    {
        return $this->belongsTo(FoodBranch::class, 'food_branch_id');
    }

    public static function paidWorkStartEffectiveFrom(): Carbon
    {
        return Carbon::parse(self::PAID_WORK_START_EFFECTIVE_FROM)->startOfDay();
    }

    public static function paidWorkStartMinutes(): int
    {
        [$h, $m] = array_map('intval', explode(':', self::PAID_WORK_START_TIME));

        return $h * 60 + $m;
    }

    public function paidWorkStartApplies(): bool
    {
        if (! $this->work_date) {
            return false;
        }

        return Carbon::parse($this->work_date)->startOfDay()->gte(self::paidWorkStartEffectiveFrom());
    }

    public function getWorkMinutesAttribute(): ?int
    {
        if (! $this->check_in_at || ! $this->check_out_at) {
            return null;
        }

        // Tính theo phần giờ trong ngày (trùng work_date) để tránh sai do timezone/ngày lưu khác
        $date = $this->work_date ? Carbon::parse($this->work_date)->startOfDay() : null;
        if (! $date) {
            $total = $this->check_out_at->diffInMinutes($this->check_in_at, false);
            if ($total < 0) {
                return null;
            }
            $total = (int) $total;
            if ($this->break_start_at && $this->break_end_at) {
                $total -= (int) $this->break_end_at->diffInMinutes($this->break_start_at, false);
            }

            return max(0, $total);
        }

        $actualIn = $this->check_in_at->hour * 60 + $this->check_in_at->minute;
        $outMinutes = $this->check_out_at->hour * 60 + $this->check_out_at->minute;
        $paidStartApplies = $this->paidWorkStartApplies();
        $paidStart = self::paidWorkStartMinutes();
        $effectiveIn = $paidStartApplies ? max($actualIn, $paidStart) : $actualIn;

        $total = $outMinutes - $effectiveIn;
        if ($total < 0) {
            $rawSpan = $outMinutes - $actualIn;
            if ($rawSpan < 0) {
                // Ca qua ngày
                $total += 24 * 60;
            } else {
                // Ra về trước khi bắt đầu khung tính tiền
                return 0;
            }
        }

        if ($this->break_start_at && $this->break_end_at) {
            $bStart = $this->break_start_at->hour * 60 + $this->break_start_at->minute;
            $bEnd = $this->break_end_at->hour * 60 + $this->break_end_at->minute;
            if ($bEnd < $bStart) {
                $bEnd += 24 * 60;
            }

            $paidEnd = $effectiveIn + $total;
            // Chỉ trừ nghỉ giao với khung giờ được tính tiền
            $overlap = max(0, min($bEnd, $paidEnd) - max($bStart, $effectiveIn));
            $total -= $overlap;
        }

        return max(0, (int) $total);
    }
}
