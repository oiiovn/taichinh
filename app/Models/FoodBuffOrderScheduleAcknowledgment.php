<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodBuffOrderScheduleAcknowledgment extends Model
{
    protected $table = 'food_buff_order_schedule_acknowledgments';

    protected $fillable = [
        'food_buff_order_schedule_id',
        'user_id',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'acknowledged_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(FoodBuffOrderSchedule::class, 'food_buff_order_schedule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
