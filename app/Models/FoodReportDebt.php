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
    ];

    protected $casts = [
        'only_tien_cong' => 'boolean',
    ];

    /** Số tiền công nợ: chỉ tiền công nếu only_tien_cong, else tổng quyết toán. */
    public function getDebtAmountAttribute(): float
    {
        $report = $this->report;
        if (! $report) {
            return 0.0;
        }
        return $this->only_tien_cong
            ? (float) $report->total_tien_cong
            : (float) $report->total_cost + (float) $report->total_tien_cong;
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
