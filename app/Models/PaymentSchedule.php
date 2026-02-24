<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSchedule extends Model
{
    protected $table = 'payment_schedules';

    protected $fillable = [
        'user_id',
        'name',
        'expected_amount',
        'amount_is_variable',
        'currency',
        'internal_note',
        'frequency',
        'interval_value',
        'next_due_date',
        'day_of_month',
        'keywords',
        'bank_account_number',
        'transfer_note_pattern',
        'amount_tolerance_pct',
        'auto_update_amount',
        'status',
        'reliability_tracking',
        'overdue_alert',
        'auto_advance_on_match',
        'reliability_score',
        'last_matched_transaction_id',
        'last_paid_date',
        'grace_window_days',
        'reminder_days',
    ];

    protected $casts = [
        'expected_amount' => 'decimal:2',
        'amount_is_variable' => 'boolean',
        'amount_tolerance_pct' => 'decimal:2',
        'auto_update_amount' => 'boolean',
        'reliability_tracking' => 'boolean',
        'overdue_alert' => 'boolean',
        'auto_advance_on_match' => 'boolean',
        'next_due_date' => 'date',
        'last_paid_date' => 'date',
        'keywords' => 'array',
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_ENDED = 'ended';

    public const FREQUENCY_MONTHLY = 'monthly';
    public const FREQUENCY_EVERY_2_MONTHS = 'every_2_months';
    public const FREQUENCY_QUARTERLY = 'quarterly';
    public const FREQUENCY_YEARLY = 'yearly';
    public const FREQUENCY_CUSTOM_DAYS = 'custom_days';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lastMatchedTransaction(): BelongsTo
    {
        return $this->belongsTo(TransactionHistory::class, 'last_matched_transaction_id');
    }
}
