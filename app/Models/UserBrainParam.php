<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBrainParam extends Model
{
    protected $table = 'user_brain_params';

    protected $fillable = [
        'user_id',
        'param_key',
        'param_value',
    ];

    protected $casts = [
        'param_value' => 'decimal:6',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
