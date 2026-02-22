<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstimatedExpense extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'amount',
        'category',
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
}
