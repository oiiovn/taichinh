<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTongquanStatistic extends Model
{
    protected $table = 'user_tongquan_statistics';

    protected $fillable = [
        'user_id',
        'name',
        'period',
        'from_date',
        'to_date',
        'thu_category_ids',
        'chi_category_ids',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'thu_category_ids' => 'array',
        'chi_category_ids' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
