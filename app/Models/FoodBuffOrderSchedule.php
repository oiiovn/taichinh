<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FoodBuffOrderSchedule extends Model
{
    protected $table = 'food_buff_order_schedules';

    protected $fillable = [
        'schedule_date',
        'branch_targets',
        'order_channel',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'schedule_date' => 'date',
            'branch_targets' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'food_buff_order_schedule_user', 'food_buff_order_schedule_id', 'user_id');
    }

    public function acknowledgments(): HasMany
    {
        return $this->hasMany(FoodBuffOrderScheduleAcknowledgment::class, 'food_buff_order_schedule_id');
    }
}
