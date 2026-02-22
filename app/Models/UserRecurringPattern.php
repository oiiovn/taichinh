<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRecurringPattern extends Model
{
    protected $table = 'user_recurring_patterns';

    protected $fillable = [
        'user_id',
        'merchant_group',
        'direction',
        'avg_amount',
        'amount_std',
        'avg_interval_days',
        'interval_std',
        'user_category_id',
        'confidence_score',
        'last_seen_at',
        'next_expected_at',
        'status',
        'match_count',
    ];

    protected $casts = [
        'avg_amount' => 'decimal:2',
        'amount_std' => 'decimal:2',
        'avg_interval_days' => 'decimal:2',
        'interval_std' => 'decimal:2',
        'confidence_score' => 'float',
        'last_seen_at' => 'datetime',
        'next_expected_at' => 'date',
        'match_count' => 'integer',
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_WEAK = 'weak';
    public const STATUS_BROKEN = 'broken';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function userCategory(): BelongsTo
    {
        return $this->belongsTo(UserCategory::class);
    }

    public function matchesTransaction(float $amount, $transactionDate): bool
    {
        $cfg = config('classification.recurring', []);
        $amountTolerancePct = $cfg['amount_tolerance_pct'] ?? 0.15;
        $dateToleranceDays = $cfg['date_tolerance_days'] ?? 5;

        $amountTolerance = $this->avg_amount * $amountTolerancePct;
        if (abs($amount - (float) $this->avg_amount) > $amountTolerance) {
            return false;
        }

        if ($this->next_expected_at === null) {
            return true;
        }

        $txDate = $transactionDate instanceof \Carbon\Carbon
            ? $transactionDate->startOfDay()
            : \Carbon\Carbon::parse($transactionDate)->startOfDay();
        $expected = \Carbon\Carbon::parse($this->next_expected_at)->startOfDay();
        $diffDays = abs($txDate->diffInDays($expected, false));

        return $diffDays <= $dateToleranceDays;
    }
}
