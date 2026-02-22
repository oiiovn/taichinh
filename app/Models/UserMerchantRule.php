<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMerchantRule extends Model
{
    protected $table = 'user_merchant_rules';

    protected $fillable = [
        'user_id',
        'merchant_key',
        'mapped_user_category_id',
        'confirmed_count',
        'confidence_score',
        'last_confirmed_at',
    ];

    protected $casts = [
        'confidence_score' => 'float',
        'last_confirmed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function userCategory(): BelongsTo
    {
        return $this->belongsTo(UserCategory::class, 'mapped_user_category_id');
    }
}
