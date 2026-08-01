<?php

namespace App\Services\Food;

use App\Models\FoodRecipeTemplate;
use App\Models\FoodRecipeTemplateItem;
use Illuminate\Support\Collection;

/**
 * Bung BOM công thức lồng công thức → nguyên liệu gốc.
 */
class RecipeBomService
{
    public const MAX_DEPTH = 12;

    /**
     * @return Collection<int, Collection<int, FoodRecipeTemplateItem>>
     */
    public function itemsGroupedForUser(int $userId): Collection
    {
        $templateIds = FoodRecipeTemplate::query()
            ->where('user_id', $userId)
            ->pluck('id');

        if ($templateIds->isEmpty()) {
            return collect();
        }

        return FoodRecipeTemplateItem::query()
            ->whereIn('food_recipe_template_id', $templateIds)
            ->get()
            ->groupBy('food_recipe_template_id');
    }

    /**
     * @return array<int, float> template_id => sản lượng / mẻ (tối thiểu 0.000001)
     */
    public function batchYieldsForUser(int $userId): array
    {
        $yields = [];
        foreach (FoodRecipeTemplate::query()->where('user_id', $userId)->get(['id', 'batch_yield']) as $tpl) {
            $yields[(int) $tpl->id] = max((float) ($tpl->batch_yield ?? 1), 0.000001);
        }

        return $yields;
    }

    /**
     * @param  Collection<int, Collection<int, FoodRecipeTemplateItem>>  $itemsByTemplate
     * @param  array<int, float>  $batchYields
     * @param  array<int, float>  $consumed  material_id => qty
     * @param  array<int, array<string, float>>|null  $via  material_id => [ma_hang => qty]
     */
    public function accumulate(
        int $templateId,
        float $multiplier,
        Collection $itemsByTemplate,
        array $batchYields,
        array &$consumed,
        ?array &$via = null,
        ?string $viaKey = null,
        array $stack = [],
        int $depth = 0
    ): void {
        if ($multiplier <= 0 || $depth > self::MAX_DEPTH) {
            return;
        }
        if (in_array($templateId, $stack, true)) {
            return;
        }

        $stack[] = $templateId;
        $lines = $itemsByTemplate->get($templateId, collect());
        $yield = $batchYields[$templateId] ?? 1.0;

        foreach ($lines as $line) {
            $qty = (float) $line->qty_per_unit;
            if ($qty <= 0) {
                continue;
            }

            if ($line->isRecipeLine()) {
                $childId = (int) $line->child_template_id;
                if ($childId <= 0) {
                    continue;
                }
                $this->accumulate(
                    $childId,
                    $multiplier * $qty,
                    $itemsByTemplate,
                    $batchYields,
                    $consumed,
                    $via,
                    $viaKey,
                    $stack,
                    $depth + 1
                );

                continue;
            }

            $mid = (int) $line->food_material_id;
            if ($mid <= 0) {
                continue;
            }
            $use = $multiplier * $qty / $yield;
            $consumed[$mid] = ($consumed[$mid] ?? 0) + $use;
            if ($via !== null && $viaKey !== null) {
                $via[$mid][$viaKey] = ($via[$mid][$viaKey] ?? 0) + $use;
            }
        }
    }

    /**
     * Kiểm tra thêm child vào parent có tạo vòng lặp không.
     */
    public function wouldCreateCycle(int $parentTemplateId, int $childTemplateId, int $userId): bool
    {
        if ($parentTemplateId === $childTemplateId) {
            return true;
        }

        $itemsByTemplate = $this->itemsGroupedForUser($userId);
        $stack = [];
        $queue = [$childTemplateId];
        $seen = [];

        while ($queue !== []) {
            $id = array_shift($queue);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            if ($id === $parentTemplateId) {
                return true;
            }
            foreach ($itemsByTemplate->get($id, collect()) as $line) {
                if ($line->isRecipeLine() && $line->child_template_id) {
                    $queue[] = (int) $line->child_template_id;
                }
            }
            if (count($seen) > 500) {
                break;
            }
        }

        return false;
    }

    /**
     * Template IDs (trực tiếp + tổ tiên) chứa material này trong BOM.
     *
     * @return list<int>
     */
    public function templateIdsUsingMaterial(int $userId, int $materialId): array
    {
        $itemsByTemplate = $this->itemsGroupedForUser($userId);

        // parentOf[child] = list of parents that include child as recipe line
        $parentsOf = [];
        $direct = [];

        foreach ($itemsByTemplate as $tplId => $lines) {
            $tplId = (int) $tplId;
            foreach ($lines as $line) {
                if ($line->isMaterialLine() && (int) $line->food_material_id === $materialId) {
                    $direct[$tplId] = true;
                }
                if ($line->isRecipeLine() && $line->child_template_id) {
                    $child = (int) $line->child_template_id;
                    $parentsOf[$child][] = $tplId;
                }
            }
        }

        $result = $direct;
        $queue = array_keys($direct);
        while ($queue !== []) {
            $id = array_shift($queue);
            foreach ($parentsOf[$id] ?? [] as $parentId) {
                if (! isset($result[$parentId])) {
                    $result[$parentId] = true;
                    $queue[] = $parentId;
                }
            }
        }

        return array_map('intval', array_keys($result));
    }

    /**
     * Qty material tiêu hao khi dùng 1 đơn vị template (đã bung nested).
     */
    public function materialQtyInTemplate(int $userId, int $templateId, int $materialId): float
    {
        $consumed = [];
        $via = null;
        $batchYields = $this->batchYieldsForUser($userId);
        $this->accumulate($templateId, 1.0, $this->itemsGroupedForUser($userId), $batchYields, $consumed, $via);

        return (float) ($consumed[$materialId] ?? 0);
    }
}
