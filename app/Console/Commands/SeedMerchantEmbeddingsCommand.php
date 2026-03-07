<?php

namespace App\Console\Commands;

use App\Models\GlobalMerchantPattern;
use App\Models\UserMerchantRule;
use App\Services\MerchantEmbeddingService;
use Illuminate\Console\Command;

class SeedMerchantEmbeddingsCommand extends Command
{
    protected $signature = 'merchant-embedding:seed
                            {--limit=500 : Số lượng merchant_key tối đa cần embed}
                            {--force : Ghi đè embedding đã tồn tại}';

    protected $description = 'Tạo embedding cho merchant_key từ UserMerchantRule và GlobalMerchantPattern (để merchant mới có thể match gần đúng).';

    public function handle(MerchantEmbeddingService $embeddingService): int
    {
        if (! $embeddingService->isEnabled()) {
            $this->warn('Merchant embedding đang tắt (merchant_embedding.enabled).');
            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        $force = $this->option('force');

        $keys = collect();

        $ruleKeys = UserMerchantRule::whereNotNull('merchant_key')
            ->where('merchant_key', '!=', '')
            ->where('merchant_key', '!=', 'unknown')
            ->distinct()
            ->pluck('merchant_key');
        $keys = $keys->merge($ruleKeys);

        $globalKeys = GlobalMerchantPattern::whereNotNull('merchant_group')
            ->where('merchant_group', '!=', '')
            ->distinct()
            ->pluck('merchant_group');
        $keys = $keys->merge($globalKeys);

        $keys = $keys->unique()->filter()->values()->take($limit)->all();

        $existing = \App\Models\MerchantEmbedding::whereIn('merchant_key', $keys)->pluck('merchant_key')->all();
        $toCreate = $force ? $keys : array_values(array_diff($keys, $existing));

        $this->info('Tổng merchant_key: ' . count($keys) . ', cần tạo mới: ' . count($toCreate));

        $bar = $this->output->createProgressBar(count($toCreate));
        $bar->start();

        foreach ($toCreate as $key) {
            $embeddingService->getOrCreateForMerchantKey($key);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Xong. merchant_embeddings có thể dùng cho phân loại theo độ tương đồng (Highlands ~ Starbucks).');

        return self::SUCCESS;
    }
}
