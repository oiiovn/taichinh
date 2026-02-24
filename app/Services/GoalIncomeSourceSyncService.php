<?php

namespace App\Services;

use App\Models\IncomeGoal;
use App\Models\IncomeSourceKeyword;
use App\Models\SystemCategory;
use App\Models\UserCategory;
use App\Models\UserIncomeSource;

/**
 * Đồng bộ UserIncomeSource + IncomeSourceKeyword từ IncomeGoal.
 * Keywords = tên các danh mục thu (category_bindings), tên chỉ là ngữ cảnh. Ô Kênh thu bổ sung.
 */
class GoalIncomeSourceSyncService
{
    /**
     * Tạo hoặc cập nhật UserIncomeSource gắn goal, đồng bộ keywords từ danh mục thu trong mục tiêu.
     */
    public function syncForGoal(IncomeGoal $goal): void
    {
        $keywords = $this->collectKeywords($goal);

        $source = UserIncomeSource::where('income_goal_id', $goal->id)->first();

        if ($source === null) {
            $source = UserIncomeSource::create([
                'user_id' => $goal->user_id,
                'name' => $goal->name,
                'source_type' => UserIncomeSource::SOURCE_TYPE_BUSINESS,
                'detection_mode' => 'hybrid',
                'is_active' => $goal->is_active ?? true,
                'created_from' => UserIncomeSource::CREATED_FROM_GOAL,
                'income_goal_id' => $goal->id,
            ]);
        } else {
            $source->update([
                'name' => $goal->name,
                'is_active' => $goal->is_active ?? true,
            ]);
        }

        $this->syncKeywords($source, $keywords);
    }

    /**
     * Thu thập keyword từ danh mục thu (category_bindings): tên user_category + system_category. Thêm income_source_keywords (Kênh thu) nếu có.
     *
     * @return array<int, string>
     */
    private function collectKeywords(IncomeGoal $goal): array
    {
        $list = [];

        $bindings = $goal->category_bindings ?? [];
        foreach ($bindings as $b) {
            if (! is_array($b)) {
                continue;
            }
            $type = $b['type'] ?? '';
            $id = isset($b['id']) ? (int) $b['id'] : 0;
            if ($id <= 0) {
                continue;
            }
            if ($type === 'user_category') {
                $cat = UserCategory::find($id);
                if ($cat && trim((string) $cat->name) !== '') {
                    $k = $this->normalizeKeyword($cat->name);
                    if ($k !== '') {
                        $list[$k] = true;
                    }
                }
            } elseif ($type === 'system_category') {
                $cat = SystemCategory::find($id);
                if ($cat && trim((string) $cat->name) !== '') {
                    $k = $this->normalizeKeyword($cat->name);
                    if ($k !== '') {
                        $list[$k] = true;
                    }
                }
            }
        }

        $extra = $goal->income_source_keywords ?? [];
        if (is_string($extra)) {
            $extra = array_filter(array_map('trim', explode(',', $extra)));
        }
        if (is_array($extra)) {
            foreach ($extra as $kw) {
                if (is_string($kw)) {
                    $k = $this->normalizeKeyword(trim($kw));
                    if ($k !== '') {
                        $list[$k] = true;
                    }
                }
            }
        }

        return array_keys($list);
    }

    private function normalizeKeyword(string $s): string
    {
        $s = trim(mb_strtolower($s));
        $s = preg_replace('/\s+/u', ' ', $s);

        return $s;
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function syncKeywords(UserIncomeSource $source, array $keywords): void
    {
        $existing = $source->keywords()->pluck('keyword', 'id')->toArray();
        $targetSet = array_fill_keys($keywords, true);

        foreach ($existing as $id => $kw) {
            if (! isset($targetSet[$kw])) {
                IncomeSourceKeyword::where('id', $id)->delete();
            } else {
                unset($targetSet[$kw]);
            }
        }

        foreach (array_keys($targetSet) as $kw) {
            IncomeSourceKeyword::create([
                'income_source_id' => $source->id,
                'keyword' => $kw,
                'match_type' => IncomeSourceKeyword::MATCH_TYPE_CONTAINS,
                'weight' => 1,
            ]);
        }
    }
}
