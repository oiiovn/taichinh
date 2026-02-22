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
        'confidence_score',
    ];

    protected $casts = [
        'confidence_score' => 'float',
    ];

    public function systemCategory(): BelongsTo
    {
        return $this->belongsTo(SystemCategory::class);
    }
}
