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
        'rating_confirmed',
        'review_content',
        'gift_code',
        'gift_item_name',
        'gift_status',
        'gift_rendered_at',
        'gift_verification_status',
        'gift_verified_at',
        'gift_revoked_at',
        'gift_rewarded_by_user_id',
        'gift_rewarded_at',
        'raw_chunk',
    ];

    protected $casts = [
        'review_date' => 'date',
        'rating' => 'integer',
        'rating_confirmed' => 'boolean',
        'gift_rendered_at' => 'datetime',
        'gift_verified_at' => 'datetime',
        'gift_revoked_at' => 'datetime',
        'gift_rewarded_at' => 'datetime',
    ];

    public function isGiftVerified(): bool
    {
        return ($this->gift_verification_status ?? null) === 'verified';
    }

    public function isGiftPendingVerification(): bool
    {
        return ($this->gift_verification_status ?? null) === 'pending';
    }

    public function isGiftRevoked(): bool
    {
        return ($this->gift_verification_status ?? null) === 'revoked';
    }

    /** Đánh giá 5 sao đã xác nhận từ import Shopee (không chỉ mặc định khi khách nhập mã). */
    public function hasConfirmedFiveStarRating(): bool
    {
        return (int) ($this->rating ?? 0) === 5 && (bool) ($this->rating_confirmed ?? false);
    }

    /** 5 sao mặc định khi khách nhập mã, chưa import xác nhận. */
    public function isDefaultFiveStarRating(): bool
    {
        return (int) ($this->rating ?? 0) === 5 && ! (bool) ($this->rating_confirmed ?? false);
    }

    public function displayRating(): ?int
    {
        return $this->rating !== null ? (int) $this->rating : null;
    }

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

