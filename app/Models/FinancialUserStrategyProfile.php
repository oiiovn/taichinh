<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Behavior Memory Layer: lưu profile chiến lược học từ phản hồi (reject ratios, tone, ưu tiên).
 */
class FinancialUserStrategyProfile extends Model
{
    protected $table = 'financial_user_strategy_profiles';

    public $timestamps = false;

    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'user_id',
        'profile_data',
    ];

    protected $casts = [
        'profile_data' => 'array',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
