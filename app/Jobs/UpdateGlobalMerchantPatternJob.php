<?php

namespace App\Jobs;

use App\Models\GlobalMerchantPattern;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateGlobalMerchantPatternJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $merchantGroup,
        public int $systemCategoryId,
        public string $direction = '',
        public string $amountBucket = ''
    ) {}

    public function handle(): void
    {
        $pattern = GlobalMerchantPattern::firstOrCreate(
            [
                'merchant_group' => $this->merchantGroup,
                'direction' => $this->direction,
                'amount_bucket' => $this->amountBucket,
                'system_category_id' => $this->systemCategoryId,
            ],
            ['merchant_key' => $this->merchantGroup, 'usage_count' => 0, 'confidence_score' => 0]
        );

        $pattern->increment('usage_count');
        $pattern->refresh();

        $score = min(0.95, 0.5 + $pattern->usage_count * 0.01);
        $pattern->update(['confidence_score' => $score]);
    }
}
