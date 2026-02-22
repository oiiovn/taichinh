<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanContractPersonal extends Model
{
    protected $table = 'loan_contract_personal';

    protected $fillable = [
        'loan_contract_id',
        'user_id',
        'notes',
        'reminder_at',
        'risk_tag',
        'meta',
    ];

    protected $casts = [
        'reminder_at' => 'datetime',
        'meta' => 'array',
    ];

    public function loanContract(): BelongsTo
    {
        return $this->belongsTo(LoanContract::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
