<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FoodReview extends Model
{
    protected $table = 'food_reviews';

    protected $fillable = [
        'user_id',
        'food_branch_id',
        'review_code',
        'review_date',
        'review_time_text',
        'customer_name',
        'rating',
        'review_content',
        'gift_code',
        'gift_item_name',
        'gift_status',
        'gift_rendered_at',
        'gift_rewarded_by_user_id',
        'gift_rewarded_at',
        'raw_chunk',
    ];

    protected $casts = [
        'review_date' => 'date',
        'rating' => 'integer',
        'gift_rendered_at' => 'datetime',
        'gift_rewarded_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(FoodBranch::class, 'food_branch_id');
    }

    public function rewardedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gift_rewarded_by_user_id');
    }

    public function giftAttempts(): HasMany
    {
        return $this->hasMany(FoodReviewGiftAttempt::class, 'food_review_id');
    }
}

