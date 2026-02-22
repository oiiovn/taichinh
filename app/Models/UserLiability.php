<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserLiability extends Model
{
    protected $table = 'user_liabilities';

    public const DIRECTION_PAYABLE = 'payable';   // Nợ — tôi đi vay
    public const DIRECTION_RECEIVABLE = 'receivable'; // Khoản cho vay — tôi cho người khác vay

    public const STATUS_ACTIVE = 'active';
    public const STATUS_CLOSED = 'closed';

    public const INTEREST_UNIT_YEARLY = 'yearly';
    public const INTEREST_UNIT_MONTHLY = 'monthly';
    public const INTEREST_UNIT_DAILY = 'daily';

    public const INTEREST_CALCULATION_SIMPLE = 'simple';
    public const INTEREST_CALCULATION_COMPOUND = 'compound';

    public const ACCRUAL_FREQUENCY_DAILY = 'daily';
    public const ACCRUAL_FREQUENCY_WEEKLY = 'weekly';
    public const ACCRUAL_FREQUENCY_MONTHLY = 'monthly';

    protected $fillable = [
        'user_id',
        'direction',
        'name',
        'principal',
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
        'principal' => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'start_date' => 'date',
        'due_date' => 'date',
        'auto_accrue' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accruals(): HasMany
    {
        return $this->hasMany(LiabilityAccrual::class, 'liability_id')->orderBy('accrued_at', 'desc');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LiabilityPayment::class, 'liability_id')->orderBy('paid_at', 'desc');
    }

    public function totalAccruedInterest(): float
    {
        return (float) $this->accruals()->sum('amount');
    }

    public function totalPaidPrincipal(): float
    {
        return (float) $this->payments()->sum('principal_portion');
    }

    public function totalPaidInterest(): float
    {
        return (float) $this->payments()->sum('interest_portion');
    }

    public function outstandingPrincipal(): float
    {
        return (float) $this->principal - $this->totalPaidPrincipal();
    }

    public function unpaidAccruedInterest(): float
    {
        return $this->totalAccruedInterest() - $this->totalPaidInterest();
    }

    public function isPayable(): bool
    {
        return $this->direction === self::DIRECTION_PAYABLE;
    }

    public function isReceivable(): bool
    {
        return $this->direction === self::DIRECTION_RECEIVABLE;
    }

    public function getDirectionLabelAttribute(): string
    {
        return $this->isPayable() ? 'Nợ (đi vay)' : 'Cho vay';
    }
}
