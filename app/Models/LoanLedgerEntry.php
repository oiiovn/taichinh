<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanLedgerEntry extends Model
{
    protected $table = 'loan_ledger_entries';

    public const TYPE_ACCRUAL = 'accrual';
    public const TYPE_PAYMENT = 'payment';
    public const TYPE_ADJUSTMENT = 'adjustment';

    public const SOURCE_SYSTEM = 'system';
    public const SOURCE_LENDER = 'lender';
    public const SOURCE_BORROWER = 'borrower';

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'loan_contract_id',
        'type',
        'principal_delta',
        'interest_delta',
        'created_by_user_id',
        'source',
        'status',
        'meta',
        'effective_date',
        'idempotency_key',
    ];

    protected $casts = [
        'principal_delta' => 'decimal:2',
        'interest_delta' => 'decimal:2',
        'effective_date' => 'date',
        'meta' => 'array',
    ];

    public function loanContract(): BelongsTo
    {
        return $this->belongsTo(LoanContract::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }
}
