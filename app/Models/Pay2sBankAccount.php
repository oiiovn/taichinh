<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pay2sBankAccount extends Model
{
    protected $table = 'pay2s_bank_accounts';

    protected $fillable = [
        'external_id',
        'account_number',
        'account_holder_name',
        'bank_code',
        'bank_name',
        'balance',
        'raw_json',
        'last_synced_at',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'raw_json' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(TransactionHistory::class, 'pay2s_bank_account_id');
    }
}
