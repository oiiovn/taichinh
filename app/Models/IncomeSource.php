<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncomeSource extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'type',
        'is_active',
        'color',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function estimatedIncomes(): HasMany
    {
        return $this->hasMany(EstimatedIncome::class, 'source_id');
    }
}
