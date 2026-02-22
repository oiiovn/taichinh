<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialInsightFeedback extends Model
{
    protected $table = 'financial_insight_feedback';

    protected $fillable = [
        'user_id',
        'insight_hash',
        'root_cause',
        'suggested_action_type',
        'feedback_type',
        'reason_code',
    ];

    public const TYPE_AGREE = 'agree';
    public const TYPE_INFEASIBLE = 'infeasible';
    public const TYPE_INCORRECT = 'incorrect';
    public const TYPE_ALTERNATIVE = 'alternative';

    public const REASON_CANNOT_INCREASE_INCOME = 'cannot_increase_income';
    public const REASON_CANNOT_REDUCE_EXPENSE = 'cannot_reduce_expense';
    public const REASON_NO_MORE_BORROWING = 'no_more_borrowing';
    public const REASON_NO_ASSET_SALE = 'no_asset_sale';

    public static function reasonCodeLabels(): array
    {
        return [
            self::REASON_CANNOT_INCREASE_INCOME => 'Không thể tăng thu',
            self::REASON_CANNOT_REDUCE_EXPENSE => 'Không thể giảm chi',
            self::REASON_NO_MORE_BORROWING => 'Không muốn vay thêm',
            self::REASON_NO_ASSET_SALE => 'Không muốn bán tài sản',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
