<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodBuffOrder extends Model
{
    protected $table = 'food_buff_orders';

    protected $fillable = [
        'user_id',
        'food_branch_id',
        'invoice_code',
        'order_date',
        'order_time_text',
        'receiver_name',
        'customer_name',
        'buff_amount',
        'labor_amount',
        'customer_reviewed',
    ];

    protected $casts = [
        'order_date' => 'date',
        'buff_amount' => 'decimal:0',
        'labor_amount' => 'decimal:0',
        'customer_reviewed' => 'boolean',
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
