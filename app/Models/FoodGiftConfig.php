<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodGiftConfig extends Model
{
    protected $table = 'food_gift_configs';

    protected $fillable = [
        'item_name',
        'item_image_path',
        'item_value',
    ];

    public static function getConfig(): self
    {
        return self::query()->firstOrCreate([], [
            'item_name' => 'Bánh Tráng Trộn',
            'item_value' => 34000,
        ]);
    }
}

