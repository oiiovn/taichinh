<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiabilityAccrual extends Model
{
    protected $table = 'liability_accruals';

    public const SOURCE_SYSTEM = 'system';
    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'liability_id',
        'amount',
        'accrued_at',
        'source',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'accrued_at' => 'date',
    ];

    public function liability(): BelongsTo
    {
        return $this->belongsTo(UserLiability::class, 'liability_id');
    }
}
