<?php

namespace App\Services;

use App\Models\SystemCategory;
use App\Models\UserCategory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TransactionClassificationGptService
{
    /**
     * @param  int|null  $userId  Khi có: dùng danh mục của user (UserCategory); khi null: dùng danh mục hệ thống (SystemCategory).
     */
    public function classify(
        string $merchantGroup,
        string $direction,
        string $amountBucket,
        ?string $descriptionSnippet = null,
        ?float $amountVnd = null,
        ?int $userId = null
    ): ?array {
        $apiKey = config('classification.gpt.api_key');
        if (empty($apiKey) || ! config('classification.gpt.enabled', false)) {
            return null;
        }

        $directionLabel = $direction === 'IN' ? 'Thu (income)' : 'Chi (expense)';
        $type = $direction === 'IN' ? 'income' : 'expense';

        $useUserCategories = $userId !== null;
        $userCategories = null;
        if ($useUserCategories) {
            $userCategories = UserCategory::where('user_id', $userId)->where('type', $type)->orderBy('id')->get();
            $list = $userCategories->pluck('name')->values()->all();
            if (empty($list)) {
                $useUserCategories = false;
                $list = ($this->getAllowedCategoriesByType())[$type] ?? [];
            }
        } else {
            $categoriesByType = $this->getAllowedCategoriesByType();
            $list = $categoriesByType[$type] ?? [];
        }

        if (empty($list)) {
            return null;
        }

        $categoriesNumbered = implode("\n", array_map(fn ($n, $i) => ($i + 1) . '. ' . $n, $list, array_keys($list)));
        $descriptionForPrompt = $descriptionSnippet !== null && $descriptionSnippet !== ''
            ? trim(mb_substr($descriptionSnippet, 0, 300))
            : '(không có mô tả)';
        $amountStr = $amountVnd !== null ? number_format((int) round(abs($amountVnd))) . ' VND' : '(không rõ)';

        $prompt = <<<PROMPT
Phân loại giao dịch tài chính sau vào ĐÚNG MỘT danh mục trong danh sách cho phép.

=== THÔNG TIN GIAO DỊCH ===
Mô tả giao dịch: {$descriptionForPrompt}
Hướng: {$directionLabel}
Số tiền: {$amountStr}
Mức tiền (bucket): {$amountBucket}
Nhóm merchant (đã chuẩn hóa): {$merchantGroup}

=== DANH SÁCH DANH MỤC ĐƯỢC PHÉP (copy nguyên văn một dòng bên dưới vào system_category_name) ===
{$categoriesNumbered}

=== YÊU CẦU ===
- Chọn 1 danh mục phù hợp nhất với mô tả (vd: mua áo/quần → Mua sắm, ăn uống → Ăn uống, tiền nhà → Hóa đơn & tiện ích).
- system_category_name PHẢI trùng chính xác một dòng trong danh sách trên (không thêm bớt ký tự).
- Trả về đúng format JSON, không markdown:

{"system_category_name": "<tên danh mục copy từ list>", "confidence": <0.0-1.0>, "reason": "<lý do ngắn>"}
PROMPT;

        try {
            $response = Http::withToken($apiKey)
                ->timeout(15)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('classification.gpt.model', 'gpt-4o-mini'),
                    'messages' => [
                        ['role' => 'system', 'content' => 'You respond only with valid JSON. No markdown, no extra text.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.2,
                ]);

            if (! $response->successful()) {
                Log::warning('GPT classification API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $body = $response->json();
            $content = $body['choices'][0]['message']['content'] ?? '';
            $content = trim($content);
            if (str_starts_with($content, '```')) {
                $content = preg_replace('/^```\w*\n?|\n?```$/', '', $content);
            }
            $decoded = json_decode($content, true);
            if (! is_array($decoded) || empty($decoded['system_category_name'])) {
                return null;
            }

            $name = $this->normalizeCategoryName(trim($decoded['system_category_name'] ?? ''));
            $confidence = isset($decoded['confidence']) ? (float) $decoded['confidence'] : 0.5;
            $reason = $decoded['reason'] ?? '';

            $matchedName = $this->matchAllowedCategory($name, $list);
            if ($matchedName === null) {
                Log::info('GPT returned category not in allowed list', ['name' => $name, 'allowed' => $list]);
                return null;
            }

            if ($useUserCategories) {
                $userCat = UserCategory::where('user_id', $userId)->where('type', $type)->where('name', $matchedName)->first();
                if (! $userCat) {
                    return null;
                }
                return [
                    'user_category_id' => $userCat->id,
                    'system_category_id' => $userCat->based_on_system_category_id,
                    'system_category_name' => $matchedName,
                    'confidence' => max(0, min(1, $confidence)),
                    'reason' => $reason,
                ];
            }

            $systemCategory = SystemCategory::where('name', $matchedName)->first();
            if (! $systemCategory) {
                return null;
            }

            return [
                'system_category_id' => $systemCategory->id,
                'system_category_name' => $matchedName,
                'confidence' => max(0, min(1, $confidence)),
                'reason' => $reason,
            ];
        } catch (\Throwable $e) {
            Log::warning('GPT classification exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    private function getAllowedCategoriesByType(): array
    {
        return SystemCategory::orderBy('type')
            ->orderBy('id')
            ->get()
            ->groupBy('type')
            ->map(fn ($c) => $c->pluck('name')->values()->all())
            ->all();
    }

    private function normalizeCategoryName(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('/\s+/u', ' ', $s);
        return $s;
    }

    /** Tìm tên danh mục trong list: exact match hoặc normalized match (bỏ khoảng trắng thừa). */
    private function matchAllowedCategory(string $returnedName, array $allowedList): ?string
    {
        if (in_array($returnedName, $allowedList, true)) {
            return $returnedName;
        }
        $normalized = $this->normalizeCategoryName($returnedName);
        foreach ($allowedList as $allowed) {
            if ($this->normalizeCategoryName($allowed) === $normalized) {
                return $allowed;
            }
        }
        return null;
    }
}
