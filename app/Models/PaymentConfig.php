<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentConfig extends Model
{
    protected $table = 'pay2s_config';

    protected $fillable = [
        'bank_id',
        'bank_name',
        'account_number',
        'account_holder',
        'branch',
        'qr_template',
    ];

    public static function getConfig(): ?self
    {
        return self::first();
    }
}
