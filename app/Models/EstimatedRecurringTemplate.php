<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstimatedRecurringTemplate extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'source_id',
        'amount',
        'frequency',
        'start_date',
        'end_date',
        'is_active',
        'note',
        'category',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'amount' => 'decimal:0',
        'is_active' => 'boolean',
    ];

    public const TYPE_INCOME = 'income';
    public const TYPE_EXPENSE = 'expense';
    public const FREQUENCY_DAILY = 'daily';
    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_MONTHLY = 'monthly';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(IncomeSource::class, 'source_id');
    }
}
