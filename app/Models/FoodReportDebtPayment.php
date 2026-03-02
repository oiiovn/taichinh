<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodReportDebtPayment extends Model
{
    protected $table = 'food_report_debt_payments';

    protected $fillable = [
        'food_report_debt_id',
        'transaction_history_id',
        'amount_paid',
    ];

    protected $casts = [
        'amount_paid' => 'integer',
    ];

    public function debt(): BelongsTo
    {
        return $this->belongsTo(FoodReportDebt::class, 'food_report_debt_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(TransactionHistory::class, 'transaction_history_id');
    }
}
