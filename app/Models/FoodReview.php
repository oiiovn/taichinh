<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'raw_chunk',
    ];

    protected $casts = [
        'review_date' => 'date',
        'rating' => 'integer',
        'gift_rendered_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(FoodBranch::class, 'food_branch_id');
    }
}

