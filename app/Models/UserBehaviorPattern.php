<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBehaviorPattern extends Model
{
    protected $table = 'user_behavior_patterns';

    protected $fillable = [
        'user_id',
        'merchant_group',
        'direction',
        'amount_bucket',
        'user_category_id',
        'usage_count',
        'confidence_score',
    ];

    protected $casts = [
        'confidence_score' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function userCategory(): BelongsTo
    {
        return $this->belongsTo(UserCategory::class);
    }
}
