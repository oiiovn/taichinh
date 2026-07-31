<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodReviewGiftAttempt extends Model
{
    public const RESULT_PAGE_OPEN = 'page_open';

    public const RESULT_NOT_FOUND = 'not_found';

    public const RESULT_EXPIRED = 'expired';

    public const RESULT_ALREADY_REWARDED = 'already_rewarded';

    public const RESULT_SUCCESS = 'success';

    protected $table = 'food_review_gift_attempts';

    protected $fillable = [
        'order_code_input',
        'order_code_normalized',
        'food_review_id',
        'result',
        'result_message',
        'gift_code',
        'ip_address',
        'user_agent',
    ];

    public static function resultLabels(): array
    {
        return [
            self::RESULT_PAGE_OPEN => 'Mở link QR',
            self::RESULT_SUCCESS => 'Thành công (hiển thị quà)',
            self::RESULT_ALREADY_REWARDED => 'Mã đã thưởng',
            self::RESULT_EXPIRED => 'Mã hết hạn',
            self::RESULT_NOT_FOUND => 'Không tìm thấy mã',
        ];
    }

    public function resultLabel(): string
    {
        return self::resultLabels()[$this->result] ?? $this->result;
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(FoodReview::class, 'food_review_id');
    }
}
