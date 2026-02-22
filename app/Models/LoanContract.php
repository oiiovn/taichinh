<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LoanContract extends Model
{
    protected $table = 'loan_contracts';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CLOSED = 'closed';

    public const INTEREST_UNIT_YEARLY = 'yearly';
    public const INTEREST_UNIT_MONTHLY = 'monthly';
    public const INTEREST_UNIT_DAILY = 'daily';

    public const INTEREST_CALCULATION_SIMPLE = 'simple';
    public const INTEREST_CALCULATION_COMPOUND = 'compound';
    public const INTEREST_CALCULATION_REDUCING = 'reducing_balance';

    public const ACCRUAL_FREQUENCY_DAILY = 'daily';
    public const ACCRUAL_FREQUENCY_WEEKLY = 'weekly';
    public const ACCRUAL_FREQUENCY_MONTHLY = 'monthly';

    protected $fillable = [
        'lender_user_id',
        'payment_schedule_enabled',
        'payment_day_of_month',
        'borrower_user_id',
        'borrower_external_name',
        'name',
        'principal_at_start',
        'interest_rate',
        'interest_unit',
        'interest_calculation',
        'accrual_frequency',
        'start_date',
        'due_date',
        'auto_accrue',
        'status',
    ];

    protected $casts = [
        'principal_at_start' => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'start_date' => 'date',
        'due_date' => 'date',
        'auto_accrue' => 'boolean',
    ];

    public function lender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lender_user_id');
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrower_user_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LoanLedgerEntry::class)->orderBy('created_at');
    }

    public function pendingPayments(): HasMany
    {
        return $this->hasMany(LoanPendingPayment::class);
    }

    public function personalFor(int $userId): ?LoanContractPersonal
    {
        return $this->personalRecords()->where('user_id', $userId)->first();
    }

    public function personalRecords(): HasMany
    {
        return $this->hasMany(LoanContractPersonal::class);
    }

    public function isLinked(): bool
    {
        return $this->borrower_user_id !== null;
    }

    public function isExternalParty(): bool
    {
        return $this->borrower_user_id === null && $this->borrower_external_name !== null;
    }

    public function borrowerDisplayName(): string
    {
        return $this->borrower?->name ?? $this->borrower_external_name ?? '—';
    }

    public function interestCalculationLabel(): string
    {
        return match ($this->interest_calculation) {
            self::INTEREST_CALCULATION_COMPOUND => 'Lãi kép',
            self::INTEREST_CALCULATION_REDUCING => 'Dư nợ giảm dần',
            default => 'Lãi đơn',
        };
    }
}
