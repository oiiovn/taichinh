<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransactionHistory extends Model
{
    use SoftDeletes;
    protected $table = 'transaction_history';

    protected $fillable = [
        'user_id',
        'depositor_user_id',
        'pay2s_bank_account_id',
        'external_id',
        'account_number',
        'amount',
        'amount_bucket',
        'type',
        'description',
        'merchant_key',
        'merchant_group',
        'merchant_vector',
        'classification_source',
        'system_category_id',
        'user_category_id',
        'income_source_id',
        'classification_status',
        'classification_confidence',
        'classification_version',
        'classification_meta',
        'transaction_date',
        'raw_json',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'datetime',
        'deleted_at' => 'datetime',
        'raw_json' => 'array',
        'merchant_vector' => 'array',
        'classification_meta' => 'array',
        'classification_confidence' => 'float',
    ];

    public const CLASSIFICATION_STATUS_PENDING = 'pending';
    public const CLASSIFICATION_STATUS_AUTO = 'auto';
    public const CLASSIFICATION_STATUS_RULE = 'rule';
    public const CLASSIFICATION_STATUS_USER_CONFIRMED = 'user_confirmed';

    /** account_number cho giao dịch tiền mặt (không trừ vào tài khoản liên kết). */
    public const ACCOUNT_TIEN_MAT = 'TIEN_MAT';

    public static function resolveAmountBucket(int $amount): string
    {
        $abs = abs($amount);
        if ($abs < 50000) {
            return 'small';
        }
        if ($abs < 500000) {
            return 'medium';
        }
        if ($abs < 5000000) {
            return 'large';
        }
        return 'very_large';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function depositor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'depositor_user_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(Pay2sBankAccount::class, 'pay2s_bank_account_id');
    }

    public function systemCategory(): BelongsTo
    {
        return $this->belongsTo(SystemCategory::class);
    }

    public function userCategory(): BelongsTo
    {
        return $this->belongsTo(UserCategory::class);
    }

    public function userIncomeSource(): BelongsTo
    {
        return $this->belongsTo(UserIncomeSource::class, 'income_source_id');
    }
}
