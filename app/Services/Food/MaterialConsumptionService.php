<?php

namespace App\Services\Food;

use App\Models\FoodMaterial;
use App\Models\FoodMaterialStock;
use App\Models\FoodMaterialStockMovement;
use App\Models\FoodProduct;
use App\Models\FoodProductRecipe;
use App\Models\FoodSalesReport;
use App\Models\FoodSalesReportItem;
use App\Services\Food\RecipeBomService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MaterialConsumptionService
{
    public function __construct(
        private readonly RecipeBomService $bom
    ) {}

    /**
     * Tính tiêu hao NL từ BC bán trong khoảng ngày, theo 1 chi nhánh.
     *
     * @return Collection<int, array{
     *   material: FoodMaterial,
     *   consumed: float,
     *   sold_via_products: array<string, float>,
     *   avg_daily: float,
     *   days_left: float|null,
     *   need_order: float,
     *   need_raw: float,
     *   order_qty: float|null,
     *   below_reorder: bool,
     *   stock: float,
     *   reorder_point: float
     * }>
     */
    public function summarizeForUser(int $userId, Carbon $from, Carbon $to, int $branchId): Collection
    {
        $materials = FoodMaterial::query()
            ->where('user_id', $userId)
            ->where('active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        if ($materials->isEmpty()) {
            return collect();
        }

        $stocks = FoodMaterialStock::query()
            ->where('food_branch_id', $branchId)
            ->whereIn('food_material_id', $materials->keys())
            ->get()
            ->keyBy('food_material_id');

        $reportIds = FoodSalesReport::query()
            ->where('user_id', $userId)
            ->where('food_branch_id', $branchId)
            ->whereBetween('report_date', [$from->toDateString(), $to->toDateString()])
            ->pluck('id');

        $soldByMaHang = FoodSalesReportItem::query()
            ->whereIn('food_sales_report_id', $reportIds)
            ->selectRaw('ma_hang, SUM(COALESCE(sl, sl_ban, 0)) as total_sl')
            ->groupBy('ma_hang')
            ->pluck('total_sl', 'ma_hang');

        $breakdown = $this->consumeSoldQty($userId, $soldByMaHang);
        $consumed = $breakdown['consumed'];
        $via = $breakdown['via'];
        $days = max(1, $from->diffInDays($to) + 1);

        return $materials->map(function (FoodMaterial $material) use ($consumed, $via, $days, $stocks) {
            $stockRow = $stocks->get($material->id);
            $used = (float) ($consumed[$material->id] ?? 0);
            $avgDaily = $used / $days;
            $stock = (float) ($stockRow?->stock_on_hand ?? 0);
            $reorder = (float) ($stockRow?->reorder_point ?? 0);
            $daysLeft = $avgDaily > 0 ? round($stock / $avgDaily, 1) : null;
            // Mục tiêu: max(điểm ĐH × 1.5, điểm ĐH + ~7 ngày tiêu thụ)
            $target = max($reorder * 1.5, $reorder + ($avgDaily * 7));
            $needRaw = max(0, round($target - $stock, 4));
            $lot = (float) ($material->order_qty ?? 0);
            $needOrder = $needRaw;
            if ($needRaw > 0 && $lot > 0) {
                // Làm tròn lên theo lô đặt (vd thiếu 150, lô 1000 → đặt 1000)
                $needOrder = ceil($needRaw / $lot) * $lot;
            }

            return [
                'material' => $material,
                'consumed' => round($used, 4),
                'sold_via_products' => $via[$material->id] ?? [],
                'avg_daily' => round($avgDaily, 4),
                'days_left' => $daysLeft,
                'need_order' => round($needOrder, 4),
                'need_raw' => $needRaw,
                'order_qty' => $lot > 0 ? $lot : null,
                'below_reorder' => $stock <= $reorder,
                'stock' => $stock,
                'reorder_point' => $reorder,
            ];
        })->values();
    }

    /**
     * @return array{
     *   rows: list<array{material: FoodMaterial, qty: float, via: array<string, float>}>,
     *   missing_products: list<array{ma_hang: string, qty: float}>,
     *   no_recipe: list<array{ma_hang: string, ten_hang: string|null, qty: float}>,
     *   applied: bool,
     *   branch_id: int|null
     * }
     */
    public function breakdownForReport(FoodSalesReport $report): array
    {
        $soldByMaHang = FoodSalesReportItem::query()
            ->where('food_sales_report_id', $report->id)
            ->selectRaw('ma_hang, SUM(COALESCE(sl, sl_ban, 0)) as total_sl')
            ->groupBy('ma_hang')
            ->pluck('total_sl', 'ma_hang');

        $result = $this->consumeSoldQty((int) $report->user_id, $soldByMaHang);
        $materials = FoodMaterial::query()
            ->where('user_id', $report->user_id)
            ->whereIn('id', array_keys($result['consumed']))
            ->get()
            ->keyBy('id');

        $rows = [];
        foreach ($result['consumed'] as $mid => $qty) {
            $material = $materials->get($mid);
            if (! $material || $qty <= 0) {
                continue;
            }
            $rows[] = [
                'material' => $material,
                'qty' => round((float) $qty, 4),
                'via' => $result['via'][$mid] ?? [],
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['material']->name, $b['material']->name));

        return [
            'rows' => $rows,
            'missing_products' => $result['missing_products'],
            'no_recipe' => $result['no_recipe'],
            'applied' => FoodMaterialStockMovement::query()
                ->where('food_sales_report_id', $report->id)
                ->where('type', FoodMaterialStockMovement::TYPE_OUT)
                ->exists(),
            'branch_id' => $report->food_branch_id ? (int) $report->food_branch_id : null,
        ];
    }

    /**
     * @return array{applied_rows: int, missing_products: int, no_recipe: int}
     */
    public function applyReportConsumption(FoodSalesReport $report): array
    {
        if (! $report->food_branch_id) {
            throw new RuntimeException('Báo cáo chưa chọn chi nhánh — không thể trừ tồn theo kho CN.');
        }

        $branchId = (int) $report->food_branch_id;
        $breakdown = $this->breakdownForReport($report);

        return DB::transaction(function () use ($report, $breakdown, $branchId) {
            $this->reverseReportConsumption($report);

            $note = 'Tiêu hao BC '.$report->report_code.' ('.$report->report_date->format('d/m/Y').')';
            $applied = 0;

            foreach ($breakdown['rows'] as $row) {
                $qty = (float) $row['qty'];
                if ($qty <= 0) {
                    continue;
                }

                $stock = FoodMaterialStock::query()
                    ->where('food_material_id', $row['material']->id)
                    ->where('food_branch_id', $branchId)
                    ->lockForUpdate()
                    ->first();

                if (! $stock) {
                    $stock = FoodMaterialStock::forMaterialBranch($row['material']->id, $branchId);
                    $stock = FoodMaterialStock::query()->whereKey($stock->id)->lockForUpdate()->first();
                }

                $stock->stock_on_hand = round((float) $stock->stock_on_hand - $qty, 4);
                $stock->save();

                FoodMaterialStockMovement::query()->create([
                    'food_material_id' => $row['material']->id,
                    'user_id' => $report->user_id,
                    'food_sales_report_id' => $report->id,
                    'food_branch_id' => $branchId,
                    'type' => FoodMaterialStockMovement::TYPE_OUT,
                    'qty' => $qty,
                    'stock_after' => $stock->stock_on_hand,
                    'note' => $note,
                ]);
                $applied++;
            }

            return [
                'applied_rows' => $applied,
                'missing_products' => count($breakdown['missing_products']),
                'no_recipe' => count($breakdown['no_recipe']),
            ];
        });
    }

    public function reverseReportConsumption(FoodSalesReport $report): void
    {
        $movements = FoodMaterialStockMovement::query()
            ->where('food_sales_report_id', $report->id)
            ->where('type', FoodMaterialStockMovement::TYPE_OUT)
            ->get();

        if ($movements->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($movements, $report) {
            foreach ($movements->groupBy(fn ($m) => $m->food_material_id.'-'.($m->food_branch_id ?? 0)) as $group) {
                $first = $group->first();
                $qty = (float) $group->sum('qty');
                $branchId = $first->food_branch_id ? (int) $first->food_branch_id : (int) ($report->food_branch_id ?? 0);
                if ($branchId <= 0) {
                    FoodMaterialStockMovement::query()->whereIn('id', $group->pluck('id'))->delete();

                    continue;
                }

                $stock = FoodMaterialStock::query()
                    ->where('food_material_id', $first->food_material_id)
                    ->where('food_branch_id', $branchId)
                    ->lockForUpdate()
                    ->first();

                if (! $stock) {
                    $stock = FoodMaterialStock::forMaterialBranch((int) $first->food_material_id, $branchId);
                    $stock = FoodMaterialStock::query()->whereKey($stock->id)->lockForUpdate()->first();
                }

                $stock->stock_on_hand = round((float) $stock->stock_on_hand + $qty, 4);
                $stock->save();

                FoodMaterialStockMovement::query()->whereIn('id', $group->pluck('id'))->delete();
            }
        });
    }

    /**
     * @param  Collection<string, mixed>  $soldByMaHang
     * @return array{
     *   consumed: array<int, float>,
     *   via: array<int, array<string, float>>,
     *   missing_products: list<array{ma_hang: string, qty: float}>,
     *   no_recipe: list<array{ma_hang: string, ten_hang: string|null, qty: float}>
     * }
     */
    private function consumeSoldQty(int $userId, Collection $soldByMaHang): array
    {
        $products = FoodProduct::query()
            ->where('user_id', $userId)
            ->get(['id', 'ma_hang', 'ten_hang', 'food_recipe_template_id'])
            ->keyBy(fn (FoodProduct $p) => trim((string) $p->ma_hang));

        $legacyRecipes = FoodProductRecipe::query()
            ->whereIn('food_product_id', $products->pluck('id'))
            ->get()
            ->groupBy('food_product_id');

        $itemsByTemplate = $this->bom->itemsGroupedForUser($userId);

        $consumed = [];
        $via = [];
        $missingProducts = [];
        $noRecipe = [];

        foreach ($soldByMaHang as $maHang => $qtySold) {
            $maHang = trim((string) $maHang);
            $qtySold = (float) $qtySold;
            if ($maHang === '' || $qtySold <= 0) {
                continue;
            }

            $product = $products->get($maHang);
            if (! $product) {
                $missingProducts[] = ['ma_hang' => $maHang, 'qty' => $qtySold];

                continue;
            }

            if ($product->food_recipe_template_id) {
                $tplId = (int) $product->food_recipe_template_id;
                $tplLines = $itemsByTemplate->get($tplId, collect());
                if ($tplLines->isNotEmpty()) {
                    $this->bom->accumulate(
                        $tplId,
                        $qtySold,
                        $itemsByTemplate,
                        $consumed,
                        $via,
                        $maHang
                    );

                    continue;
                }
            }

            $lines = $legacyRecipes->get($product->id, collect());
            if ($lines->isEmpty()) {
                $noRecipe[] = [
                    'ma_hang' => $maHang,
                    'ten_hang' => $product->ten_hang,
                    'qty' => $qtySold,
                ];

                continue;
            }

            foreach ($lines as $line) {
                $mid = (int) $line->food_material_id;
                $use = $qtySold * (float) $line->qty_per_unit;
                $consumed[$mid] = ($consumed[$mid] ?? 0) + $use;
                $via[$mid][$maHang] = ($via[$mid][$maHang] ?? 0) + $use;
            }
        }

        return [
            'consumed' => $consumed,
            'via' => $via,
            'missing_products' => $missingProducts,
            'no_recipe' => $noRecipe,
        ];
    }
}
