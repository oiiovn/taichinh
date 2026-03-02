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
        'interval_variance',
        'amount_cv',
        'miss_streak',
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
        'interval_variance' => 'float',
        'amount_cv' => 'float',
        'miss_streak' => 'integer',
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

    /**
     * Confidence v3: 0.5*interval_stability + 0.3*amount_stability + 0.2*streak_consistency.
     */
    public function getCompositeConfidence(): float
    {
        $intervalStability = 1.0;
        if ($this->interval_std > 0 && $this->avg_interval_days > 0) {
            $cv = (float) $this->interval_std / (float) $this->avg_interval_days;
            $intervalStability = max(0, 1.0 - min(1.0, $cv * 3));
        }
        $amountStability = 1.0;
        if (isset($this->amount_cv) && $this->amount_cv !== null) {
            $amountStability = max(0, 1.0 - min(1.0, (float) $this->amount_cv * 2));
        } elseif ($this->amount_std > 0 && $this->avg_amount > 0) {
            $cv = (float) $this->amount_std / (float) $this->avg_amount;
            $amountStability = max(0, 1.0 - min(1.0, $cv * 2));
        }
        $streakConsistency = 1.0;
        if (isset($this->miss_streak)) {
            $streakConsistency = max(0, 1.0 - min(1.0, (int) $this->miss_streak / 5.0));
        }
        return (float) (0.5 * $intervalStability + 0.3 * $amountStability + 0.2 * $streakConsistency);
    }
}
