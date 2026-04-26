<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FoodReportDebt extends Model
{
    protected $table = 'food_report_debts';

    protected $fillable = [
        'food_sales_report_id',
        'debtor_user_id',
        'only_tien_cong',
        'only_tien_cong_khung_gio',
        'deduction_amount',
        'addition_amount',
    ];

    protected $casts = [
        'only_tien_cong' => 'boolean',
        'only_tien_cong_khung_gio' => 'boolean',
        'deduction_amount' => 'decimal:0',
        'addition_amount' => 'decimal:0',
    ];

    /** Tổng trước khi trừ (tiền công+thưởng nếu only_tien_cong, else tổng quyết toán). */
    public function getBaseAmountAttribute(): float
    {
        $report = $this->report;
        if (! $report) {
            return 0.0;
        }
        if ($this->only_tien_cong_khung_gio) {
            return $this->calculateLaborAmountByTimeWindow($report, '16:30', '22:00');
        }
        $bonus = (float) ($report->bonus ?? 0);
        return $this->only_tien_cong
            ? (float) $report->total_tien_cong + $bonus
            : (float) $report->total_cost + (float) $report->total_tien_cong + $bonus;
    }

    /** Số tiền trừ công nợ (đã nhập khi tạo). */
    public function getDeductionAmountValueAttribute(): float
    {
        return (float) ($this->attributes['deduction_amount'] ?? 0);
    }

    /** Số tiền cộng thêm công nợ (đã nhập khi tạo). */
    public function getAdditionAmountValueAttribute(): float
    {
        return (float) ($this->attributes['addition_amount'] ?? 0);
    }

    /** Số tiền công nợ thực: base - deduction + addition (tối thiểu 0). */
    public function getDebtAmountAttribute(): float
    {
        return max(0.0, $this->base_amount - $this->deduction_amount_value + $this->addition_amount_value);
    }

    /** Chi tiết để hiển thị: ['base' => ..., 'deduction' => ..., 'addition' => ..., 'debt' => ...] */
    public function getDebtDetailAttribute(): array
    {
        return [
            'base' => $this->base_amount,
            'deduction' => $this->deduction_amount_value,
            'addition' => $this->addition_amount_value,
            'debt' => $this->debt_amount,
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(FoodSalesReport::class, 'food_sales_report_id');
    }

    public function debtor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'debtor_user_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(FoodReportDebtPayment::class, 'food_report_debt_id');
    }

    private function calculateLaborAmountByTimeWindow(FoodSalesReport $report, string $fromTime, string $toTime): float
    {
        $fromMinutes = $this->minutesFromTimeString($fromTime);
        $toMinutes = $this->minutesFromTimeString($toTime);
        if ($fromMinutes === null || $toMinutes === null) {
            return 0.0;
        }

        $items = $report->relationLoaded('items')
            ? $report->items
            : $report->items()->get();

        $orders = [];
        foreach ($items as $item) {
            $invoice = trim((string) ($item->ma_hoa_don ?? ''));
            if ($invoice === '') {
                $invoice = '_';
            }
            if (! isset($orders[$invoice])) {
                $orders[$invoice] = [
                    'order_minutes' => null,
                    'total_cost' => 0.0,
                ];
            }

            $parsedMinutes = $this->extractMinutesFromDateTime((string) ($item->thoi_gian ?? ''));
            if ($parsedMinutes !== null && ($orders[$invoice]['order_minutes'] === null || $parsedMinutes > $orders[$invoice]['order_minutes'])) {
                $orders[$invoice]['order_minutes'] = $parsedMinutes;
            }

            $giaVon = (float) ($item->gia_von_unit ?? 0);
            $sl = (float) ($item->sl ?? $item->sl_ban ?? 0);
            $orders[$invoice]['total_cost'] += $giaVon * $sl;
        }

        $totalLabor = 0.0;
        foreach ($orders as $order) {
            $minutes = $order['order_minutes'];
            if ($minutes === null || $minutes < $fromMinutes || $minutes > $toMinutes) {
                continue;
            }

            $totalLabor += $order['total_cost'] > 60000 ? 20000 : 10000;
        }

        return $totalLabor;
    }

    private function extractMinutesFromDateTime(string $dateTime): ?int
    {
        $dateTime = trim($dateTime);
        if ($dateTime === '') {
            return null;
        }

        $parts = preg_split('/\s+/', $dateTime);
        $timePart = (string) end($parts);
        if (! preg_match('/^(\d{1,2}):(\d{2})(:\d{2})?$/', $timePart, $matches)) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];
        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return null;
        }

        return ($hour * 60) + $minute;
    }

    private function minutesFromTimeString(string $time): ?int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', trim($time), $matches)) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];
        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return null;
        }

        return ($hour * 60) + $minute;
    }
}
//thêm nút xử lý công nợ vào trong phần này cho phep trừ tiền quyết toán.