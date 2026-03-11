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
        'deduction_amount',
    ];

    protected $casts = [
        'only_tien_cong' => 'boolean',
        'deduction_amount' => 'decimal:0',
    ];

    /** Tổng trước khi trừ (tiền công+thưởng nếu only_tien_cong, else tổng quyết toán). */
    public function getBaseAmountAttribute(): float
    {
        $report = $this->report;
        if (! $report) {
            return 0.0;
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

    /** Số tiền công nợ thực: base - deduction (tối thiểu 0). */
    public function getDebtAmountAttribute(): float
    {
        return max(0.0, $this->base_amount - $this->deduction_amount_value);
    }

    /** Chi tiết để hiển thị: ['base' => ..., 'deduction' => ..., 'debt' => ...] */
    public function getDebtDetailAttribute(): array
    {
        return [
            'base' => $this->base_amount,
            'deduction' => $this->deduction_amount_value,
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
}
//thêm nút xử lý công nợ vào trong phần này cho phep trừ tiền quyết toán.