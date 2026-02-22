<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBankAccount extends Model
{
    protected $table = 'user_bank_accounts';

    protected $fillable = [
        'user_id',
        'bank_code',
        'account_type',
        'api_type',
        'full_name',
        'email',
        'phone',
        'id_number',
        'account_number',
        'virtual_account_prefix',
        'virtual_account_suffix',
        'company_name',
        'login_username',
        'login_password',
        'tax_code',
        'transaction_type',
        'company_code',
        'agreed_terms',
        'external_id',
        'last_synced_at',
        'raw_json',
    ];

    protected $casts = [
        'agreed_terms' => 'boolean',
        'last_synced_at' => 'datetime',
        'raw_json' => 'array',
    ];

    protected $hidden = [
        'login_password',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
