<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserCategory extends Model
{
    protected $fillable = ['user_id', 'name', 'type', 'based_on_system_category_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function systemCategory(): BelongsTo
    {
        return $this->belongsTo(SystemCategory::class, 'based_on_system_category_id');
    }

    public function transactionHistories(): HasMany
    {
        return $this->hasMany(TransactionHistory::class, 'user_category_id');
    }
}
