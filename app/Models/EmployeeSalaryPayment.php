<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalaryPayment extends Model
{
    public const TYPE_SALARY = 'salary';

    public const TYPE_BONUS = 'bonus';

    public const TYPE_PARTIAL = 'partial';

    public const TYPE_OTHER = 'other';

    public const METHOD_CASH = 'cash';

    public const METHOD_BANK = 'bank_transfer';

    public const METHOD_OTHER = 'other';

    protected $fillable = [
        'employee_id',
        'pay_period_month',
        'payment_type',
        'amount',
        'payment_method',
        'note',
        'paid_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'pay_period_month' => 'date',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public static function paymentTypeLabels(): array
    {
        return [
            self::TYPE_SALARY => 'Lương tháng',
            self::TYPE_BONUS => 'Thưởng',
            self::TYPE_PARTIAL => 'Trả một phần',
            self::TYPE_OTHER => 'Khác',
        ];
    }

    public static function paymentMethodLabels(): array
    {
        return [
            self::METHOD_CASH => 'Tiền mặt',
            self::METHOD_BANK => 'Chuyển khoản',
            self::METHOD_OTHER => 'Khác',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
