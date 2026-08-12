<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FoodSalesReport extends Model
{
    protected $table = 'food_sales_reports';

    protected $fillable = [
        'user_id',
        'food_branch_id',
        'report_code',
        'report_date',
        'total_orders',
        'total_cost',
        'total_tien_cong',
        'bonus',
        'doanh_so',
        'phi_buff',
        'phi_ads',
        'uploaded_at',
        'note',
    ];

    protected $casts = [
        'report_date' => 'date',
        'uploaded_at' => 'datetime',
        'total_cost' => 'decimal:4',
        'total_tien_cong' => 'decimal:0',
        'bonus' => 'decimal:0',
        'doanh_so' => 'decimal:0',
        'phi_buff' => 'decimal:0',
        'phi_ads' => 'decimal:0',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(FoodBranch::class, 'food_branch_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FoodSalesReportItem::class, 'food_sales_report_id');
    }

    public function debts(): HasMany
    {
        return $this->hasMany(FoodReportDebt::class, 'food_sales_report_id');
    }

    /** Quyết toán = Tổng vốn + Tiền công (nếu chi nhánh bật) + Thưởng */
    public function laborIncludedInSettlement(): bool
    {
        if (! $this->relationLoaded('branch')) {
            $this->loadMissing('branch');
        }

        return $this->branch?->includesLaborInSettlement() ?? true;
    }

    public function settlementTienCong(): float
    {
        return $this->laborIncludedInSettlement() ? (float) $this->total_tien_cong : 0.0;
    }

    public function getQuyetToanAttribute(): float
    {
        return (float) $this->total_cost + $this->settlementTienCong() + (float) ($this->bonus ?? 0);
    }

    /** Lợi nhuận = doanh_so - quyet_toan - phi_buff - phi_ads (khi đã nhập doanh số) */
    public function getLoiNhuanAttribute(): ?float
    {
        if ($this->doanh_so === null) {
            return null;
        }

        return (float) $this->doanh_so
            - $this->quyet_toan
            - (float) ($this->phi_buff ?? 0)
            - (float) ($this->phi_ads ?? 0);
    }
}
