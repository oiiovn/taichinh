<?php

namespace App\Console\Commands;

use App\Services\Food\FoodReviewGiftVerificationService;
use Illuminate\Console\Command;

class VerifyReviewGiftsCommand extends Command
{
    protected $signature = 'food:verify-review-gifts';

    protected $description = 'Xác minh quà đánh giá 5 sao sau 4h; huỷ quà nếu chưa có đánh giá 5 sao.';

    public function handle(FoodReviewGiftVerificationService $service): int
    {
        $result = $service->processDueVerifications();
        $this->info("Đã xác minh: {$result['verified']}, đã huỷ: {$result['revoked']}.");

        return self::SUCCESS;
    }
}
