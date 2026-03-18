<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    protected $fillable = [
        'employee_id',
        'work_date',
        'check_in_at',
        'check_out_at',
        'break_start_at',
        'break_end_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'break_start_at' => 'datetime',
            'break_end_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function getWorkMinutesAttribute(): ?int
    {
        if (! $this->check_in_at || ! $this->check_out_at) {
            return null;
        }
        // Tính theo phần giờ trong ngày (trùng work_date) để tránh sai do timezone/ngày lưu khác
        $date = $this->work_date ? \Carbon\Carbon::parse($this->work_date)->startOfDay() : null;
        if (! $date) {
            $total = $this->check_out_at->diffInMinutes($this->check_in_at, false);
            if ($total < 0) {
                return null;
            }
            $total = (int) $total;
        } else {
            $inMinutes = $this->check_in_at->hour * 60 + $this->check_in_at->minute;
            $outMinutes = $this->check_out_at->hour * 60 + $this->check_out_at->minute;
            $total = $outMinutes - $inMinutes;
            if ($total < 0) {
                $total += 24 * 60;
            }
        }
        if ($this->break_start_at && $this->break_end_at) {
            $breakMins = $this->break_end_at->hour * 60 + $this->break_end_at->minute
                - ($this->break_start_at->hour * 60 + $this->break_start_at->minute);
            if ($breakMins < 0) {
                $breakMins += 24 * 60;
            }
            $total -= $breakMins;
        }

        return max(0, (int) $total);
    }
}
