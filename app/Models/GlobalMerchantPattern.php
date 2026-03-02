<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlobalMerchantPattern extends Model
{
    protected $table = 'global_merchant_patterns';

    protected $fillable = [
        'merchant_key',
        'merchant_group',
        'direction',
        'amount_bucket',
        'system_category_id',
        'usage_count',
        'correct_count',
        'wrong_count',
        'last_used_at',
        'last_wrong_at',
        'decay_factor',
        'confidence_score',
    ];

    protected $casts = [
        'confidence_score' => 'float',
        'last_used_at' => 'datetime',
        'last_wrong_at' => 'datetime',
        'decay_factor' => 'float',
    ];

    /** usage_count = lần dùng pattern; wrong_count = lần user sửa. accuracy = (usage - wrong) / usage */
    public function getAccuracyAttribute(): float
    {
        if ($this->usage_count <= 0) {
            return 1.0;
        }
        return (float) ($this->usage_count - $this->wrong_count) / $this->usage_count;
    }

    public function getConfidenceFromAccuracy(): float
    {
        $accuracy = $this->accuracy;
        $usage = $this->usage_count;
        if ($usage <= 0) {
            return 0.5;
        }
        return (float) min(0.95, $accuracy * log($usage + 1) / 5.0);
    }

    public function systemCategory(): BelongsTo
    {
        return $this->belongsTo(SystemCategory::class);
    }
}
