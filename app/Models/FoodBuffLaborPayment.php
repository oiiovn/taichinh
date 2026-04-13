<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodBuffLaborPayment extends Model
{
    protected $table = 'food_buff_labor_payments';

    public const METHOD_CASH = 'cash';
    public const METHOD_BANK = 'bank';

    protected $fillable = [
        'payer_user_id',
        'paid_user_id',
        'amount',
        'payment_method',
        'note',
        'paid_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:0',
            'paid_at' => 'datetime',
        ];
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_user_id');
    }

    public function paidUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
