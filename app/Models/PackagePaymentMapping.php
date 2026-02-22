<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackagePaymentMapping extends Model
{
    protected $table = 'package_payment_mappings';

    protected $fillable = [
        'user_id',
        'plan_key',
        'mapping_code',
        'amount',
        'term_months',
        'status',
        'transaction_history_id',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'term_months' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactionHistory(): BelongsTo
    {
        return $this->belongsTo(TransactionHistory::class, 'transaction_history_id');
    }
}
