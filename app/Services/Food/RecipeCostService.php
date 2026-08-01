<?php

namespace App\Services\Food;

use App\Models\FoodMaterial;

class RecipeCostService
{
    public function __construct(
        private readonly RecipeBomService $bom
    ) {}

    /**
     * Giá vốn 1 đơn vị công thức (đã bung CT lồng) theo giá nhập NL.
     *
     * @return array{
     *     total: int,
     *     missing_price_count: int,
     *     rows: list<array{material_id: int, name: string, unit: string, qty: float, type: string, unit_cost: ?int, line_cost: ?int}>
     * }
     */
    public function forTemplate(int $userId, int $templateId): array
    {
        $itemsByTemplate = $this->bom->itemsGroupedForUser($userId);
        $batchYields = $this->bom->batchYieldsForUser($userId);
        $consumed = [];
        $via = null;
        $this->bom->accumulate($templateId, 1.0, $itemsByTemplate, $batchYields, $consumed, $via);

        return $this->summarizeConsumed($consumed);
    }

    /**
     * @param  list<int>  $templateIds
     * @return array<int, array{total: int, missing_price_count: int}>
     */
    public function totalsForTemplates(int $userId, array $templateIds): array
    {
        $templateIds = array_values(array_unique(array_map('intval', $templateIds)));
        $result = [];
        foreach ($templateIds as $id) {
            $result[$id] = ['total' => 0, 'missing_price_count' => 0];
        }
        if ($templateIds === []) {
            return $result;
        }

        $itemsByTemplate = $this->bom->itemsGroupedForUser($userId);
        $batchYields = $this->bom->batchYieldsForUser($userId);

        foreach ($templateIds as $templateId) {
            $consumed = [];
            $via = null;
            $this->bom->accumulate($templateId, 1.0, $itemsByTemplate, $batchYields, $consumed, $via);
            $summary = $this->summarizeConsumed($consumed);
            $result[$templateId] = [
                'total' => $summary['total'],
                'missing_price_count' => $summary['missing_price_count'],
            ];
        }

        return $result;
    }

    /**
     * @param  array<int, float>  $consumed
     * @return array{
     *     total: int,
     *     missing_price_count: int,
     *     rows: list<array{material_id: int, name: string, unit: string, qty: float, type: string, unit_cost: ?int, line_cost: ?int}>
     * }
     */
    private function summarizeConsumed(array $consumed): array
    {
        if ($consumed === []) {
            return ['total' => 0, 'missing_price_count' => 0, 'rows' => []];
        }

        $materials = FoodMaterial::query()
            ->whereIn('id', array_keys($consumed))
            ->get()
            ->keyBy('id');

        $rows = [];
        $total = 0;
        $missing = 0;

        foreach ($consumed as $materialId => $qty) {
            $qty = (float) $qty;
            if ($qty <= 0) {
                continue;
            }
            $material = $materials->get((int) $materialId);
            if (! $material) {
                continue;
            }

            $unitCost = $material->last_unit_cost !== null ? (int) $material->last_unit_cost : null;
            $lineCost = $unitCost !== null ? (int) round($qty * $unitCost) : null;

            if ($unitCost === null) {
                $missing++;
            } else {
                $total += $lineCost ?? 0;
            }

            $rows[] = [
                'material_id' => (int) $materialId,
                'name' => $material->name,
                'unit' => $material->unit,
                'qty' => $qty,
                'type' => $material->type,
                'unit_cost' => $unitCost,
                'line_cost' => $lineCost,
            ];
        }

        usort($rows, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        return [
            'total' => $total,
            'missing_price_count' => $missing,
            'rows' => $rows,
        ];
    }
}
