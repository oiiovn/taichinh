<?php

namespace App\Console\Commands;

use App\Services\TransactionClassificationGptService;
use Illuminate\Console\Command;

class TestClassificationGptCommand extends Command
{
    protected $signature = 'classification:test-gpt
                            {--desc= : Mô tả giao dịch mẫu}
                            {--amount=50000 : Số tiền VND}
                            {--dir=OUT : IN hoặc OUT}';

    protected $description = 'Kiểm tra phân loại GPT: cấu hình + gọi API thật và in kết quả.';

    public function handle(TransactionClassificationGptService $gptService): int
    {
        $enabled = config('classification.gpt.enabled', false);
        $apiKey = config('classification.gpt.api_key', '');
        $model = config('classification.gpt.model', 'gpt-4o-mini');
        $hasKey = ! empty(trim($apiKey));

        $this->line('--- Cấu hình ---');
        $this->line('GPT enabled: ' . ($enabled ? 'có' : 'không'));
        $this->line('API key: ' . ($hasKey ? '(đã set, ' . strlen($apiKey) . ' ký tự)' : 'chưa set'));
        $this->line('Model: ' . $model);

        if (! $enabled || ! $hasKey) {
            $this->warn('Bật GPT và set OPENAI_API_KEY trong .env (GPT_CLASSIFICATION_ENABLED=true) rồi chạy lại.');
            return 1;
        }

        $desc = $this->option('desc') ?: 'Grab Food - Cơm trưa văn phòng';
        $amount = (float) $this->option('amount');
        $dir = strtoupper($this->option('dir')) === 'IN' ? 'IN' : 'OUT';
        $bucket = $amount <= 0 ? 'unknown' : (\App\Models\TransactionHistory::resolveAmountBucket((int) round($amount)));
        $merchantGroup = 'grab_food'; // mẫu

        $this->line('');
        $this->line('--- Gọi API GPT (1 request) ---');
        $this->line("Mô tả: {$desc}");
        $this->line("Hướng: {$dir} | Số tiền: " . number_format((int) round($amount)) . ' VND | Bucket: ' . $bucket);
        $this->line('Merchant group: ' . $merchantGroup);

        $result = $gptService->classify($merchantGroup, $dir, $bucket, $desc, $amount);

        $this->line('');
        if ($result === null) {
            $this->error('API không trả về kết quả (hoặc lỗi). Xem log storage/logs/laravel.log.');
            return 1;
        }
        $this->info('Đã gọi API GPT và nhận kết quả:');
        $this->line('  Danh mục: ' . ($result['system_category_name'] ?? ''));
        $this->line('  system_category_id: ' . ($result['system_category_id'] ?? ''));
        $this->line('  confidence: ' . ($result['confidence'] ?? ''));
        $this->line('  reason: ' . ($result['reason'] ?? ''));
        return 0;
    }
}
