<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanPendingPayment extends Model
{
    protected $table = 'loan_pending_payments';

    public const STATUS_AWAITING = 'awaiting_payment';
    public const STATUS_MATCHED_BANK = 'matched_bank';
    public const STATUS_PENDING_CONFIRM = 'pending_counterparty_confirm';
    public const STATUS_CONFIRMED = 'confirmed';

    public const PAYMENT_METHOD_BANK = 'bank';
    public const PAYMENT_METHOD_CASH = 'cash';

    protected $fillable = [
        'loan_contract_id',
        'due_date',
        'expected_principal',
        'expected_interest',
        'match_content',
        'status',
        'transaction_history_id',
        'bank_transaction_ref',
        'payment_method',
        'recorded_by_user_id',
        'recorded_at',
        'confirmed_by_user_id',
        'confirmed_at',
        'loan_ledger_entry_id',
        'meta',
    ];

    protected $casts = [
        'expected_principal' => 'decimal:2',
        'expected_interest' => 'decimal:2',
        'due_date' => 'date',
        'recorded_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function loanContract(): BelongsTo
    {
        return $this->belongsTo(LoanContract::class);
    }

    public function transactionHistory(): BelongsTo
    {
        return $this->belongsTo(TransactionHistory::class);
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    public function loanLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(LoanLedgerEntry::class);
    }

    public function isAwaiting(): bool
    {
        return $this->status === self::STATUS_AWAITING;
    }

    public function isPendingConfirm(): bool
    {
        return $this->status === self::STATUS_PENDING_CONFIRM;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function needsCounterpartyConfirm(int $userId): bool
    {
        if ($this->status !== self::STATUS_PENDING_CONFIRM) {
            return false;
        }
        $contract = $this->loanContract;
        $recordedBy = (int) $this->recorded_by_user_id;
        if ($recordedBy === 0) {
            return false;
        }
        $counterparty = $contract->lender_user_id === $recordedBy ? $contract->borrower_user_id : $contract->lender_user_id;
        return $counterparty !== null && (int) $counterparty === $userId;
    }
}
