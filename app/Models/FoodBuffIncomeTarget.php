<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodBuffIncomeTarget extends Model
{
    protected $table = 'food_buff_income_targets';

    protected $fillable = [
        'user_id',
        'target_month',
        'target_amount',
        'created_by_user_id',
    ];

    protected $casts = [
        'target_month' => 'date',
        'target_amount' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
