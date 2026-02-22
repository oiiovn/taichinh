<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstimatedIncome extends Model
{
    protected $fillable = [
        'user_id',
        'source_id',
        'date',
        'amount',
        'note',
        'mode',
        'recurring_template_id',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:0',
    ];

    public const MODE_MANUAL = 'manual';
    public const MODE_RECURRING = 'recurring';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(IncomeSource::class, 'source_id');
    }
}
