<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pay2sApiConfig extends Model
{
    protected $table = 'pay2s_api_config';

    protected $fillable = [
        'partner_code',
        'access_key',
        'secret_key',
        'base_url',
        'path_accounts',
        'path_transactions',
        'bank_accounts',
        'fetch_begin',
        'fetch_end',
        'fetch_chunk_days',
        'webhook_bearer_token',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function getConfig(): ?self
    {
        return self::first();
    }
}
