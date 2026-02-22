<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiabilityPayment extends Model
{
    protected $table = 'liability_payments';

    protected $fillable = [
        'liability_id',
        'amount',
        'paid_at',
        'principal_portion',
        'interest_portion',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'principal_portion' => 'decimal:2',
        'interest_portion' => 'decimal:2',
        'paid_at' => 'date',
    ];

    public function liability(): BelongsTo
    {
        return $this->belongsTo(UserLiability::class, 'liability_id');
    }
}
