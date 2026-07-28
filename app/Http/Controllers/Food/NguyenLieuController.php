<?php

namespace App\Http\Controllers\Food;

use App\Http\Controllers\Controller;
use App\Models\FoodBranch;
use App\Models\FoodMaterial;
use App\Models\FoodMaterialStock;
use App\Models\FoodMaterialStockMovement;
use App\Models\FoodProduct;
use App\Models\FoodProductRecipe;
use App\Models\FoodRecipeTemplate;
use App\Models\FoodRecipeTemplateItem;
use App\Services\Food\MaterialConsumptionService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NguyenLieuController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $branches = FoodBranch::query()->where('user_id', $user->id)->orderBy('name')->get();
        if ($branches->isEmpty()) {
            return view('pages.food.nguyen-lieu.index', [
                'title' => 'Nguyên liệu & bao bì',
                'materials' => collect(),
                'branches' => $branches,
                'branch' => null,
                'typeFilter' => $request->input('type'),
                'lowOnly' => $request->boolean('low_only'),
                'lowCount' => 0,
                'typeLabels' => FoodMaterial::typeLabels(),
                'materialUsages' => [],
            ]);
        }

        $branch = $this->resolveBranch($request, $branches);
        $this->ensureStocksForBranch($user->id, $branch->id);

        $type = $request->input('type');
        $q = FoodMaterial::query()->where('user_id', $user->id)->orderBy('type')->orderBy('name');
        if (in_array($type, [FoodMaterial::TYPE_NGUYEN_LIEU, FoodMaterial::TYPE_BAO_BI], true)) {
            $q->where('type', $type);
        }

        $materials = $q->get();
        $stocks = FoodMaterialStock::query()
            ->where('food_branch_id', $branch->id)
            ->whereIn('food_material_id', $materials->pluck('id'))
            ->get()
            ->keyBy('food_material_id');

        foreach ($materials as $m) {
            $m->setRelation('branchStock', $stocks->get($m->id));
        }

        if ($request->boolean('low_only')) {
            $materials = $materials->filter(fn (FoodMaterial $m) => $m->needsReorder())->values();
        }

        $materialUsages = $this->buildMaterialUsages($user->id, $materials->pluck('id'));

        $lowCount = FoodMaterialStock::query()
            ->where('food_branch_id', $branch->id)
            ->whereIn('food_material_id', FoodMaterial::query()->where('user_id', $user->id)->where('active', true)->select('id'))
            ->whereColumn('stock_on_hand', '<=', 'reorder_point')
            ->count();

        return view('pages.food.nguyen-lieu.index', [
            'title' => 'Nguyên liệu & bao bì',
            'materials' => $materials,
            'branches' => $branches,
            'branch' => $branch,
            'typeFilter' => $type,
            'lowOnly' => $request->boolean('low_only'),
            'lowCount' => $lowCount,
            'typeLabels' => FoodMaterial::typeLabels(),
            'materialUsages' => $materialUsages,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'food_branch_id' => ['required', 'integer', 'exists:food_branches,id'],
            'code' => ['nullable', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:nguyen_lieu,bao_bi'],
            'unit' => ['required', 'string', 'max:32'],
            'order_qty' => ['nullable', 'numeric', 'min:0'],
            'stock_on_hand' => ['nullable', 'numeric', 'min:0'],
            'reorder_point' => ['nullable', 'numeric', 'min:0'],
            'last_unit_cost' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $branch = FoodBranch::query()->where('user_id', $user->id)->findOrFail($validated['food_branch_id']);

        $code = filled($validated['code'] ?? null) ? trim((string) $validated['code']) : null;
        if ($code !== null) {
            $exists = FoodMaterial::query()
                ->where('user_id', $user->id)
                ->where('code', $code)
                ->exists();
            if ($exists) {
                return back()->withInput()->with('error', 'Mã nguyên liệu đã tồn tại.');
            }
        }

        $stockQty = (float) ($validated['stock_on_hand'] ?? 0);
        $reorder = (float) ($validated['reorder_point'] ?? 0);
        $orderQty = isset($validated['order_qty']) && $validated['order_qty'] !== null && $validated['order_qty'] !== ''
            ? (float) $validated['order_qty']
            : null;
        if ($orderQty !== null && $orderQty <= 0) {
            $orderQty = null;
        }

        $material = DB::transaction(function () use ($user, $validated, $code, $branch, $stockQty, $reorder, $orderQty, $request) {
            $material = FoodMaterial::query()->create([
                'user_id' => $user->id,
                'code' => $code,
                'name' => trim($validated['name']),
                'type' => $validated['type'],
                'unit' => trim($validated['unit']),
                'order_qty' => $orderQty,
                'last_unit_cost' => isset($validated['last_unit_cost'])
                    ? (int) round((float) $validated['last_unit_cost'])
                    : null,
                'note' => $validated['note'] ?? null,
                'active' => $request->boolean('active', true),
            ]);

            $allBranches = FoodBranch::query()->where('user_id', $user->id)->pluck('id');
            foreach ($allBranches as $bid) {
                $isSelected = (int) $bid === (int) $branch->id;
                FoodMaterialStock::query()->create([
                    'food_material_id' => $material->id,
                    'food_branch_id' => $bid,
                    'stock_on_hand' => $isSelected ? $stockQty : 0,
                    'reorder_point' => $reorder,
                ]);
            }

            if ($stockQty > 0) {
                FoodMaterialStockMovement::query()->create([
                    'food_material_id' => $material->id,
                    'user_id' => $user->id,
                    'food_branch_id' => $branch->id,
                    'type' => FoodMaterialStockMovement::TYPE_IN,
                    'qty' => $stockQty,
                    'stock_after' => $stockQty,
                    'note' => 'Tồn đầu khi tạo ('.$branch->name.')',
                ]);
            }

            return $material;
        });

        return redirect()
            ->route('food.nguyen-lieu', ['branch_id' => $branch->id])
            ->with('success', 'Đã thêm nguyên liệu/bao bì.');
    }

    public function update(Request $request, FoodMaterial $nguyenLieu): RedirectResponse
    {
        $user = $request->user();
        if (! $user || (int) $nguyenLieu->user_id !== (int) $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'food_branch_id' => ['required', 'integer', 'exists:food_branches,id'],
            'code' => ['nullable', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:nguyen_lieu,bao_bi'],
            'unit' => ['required', 'string', 'max:32'],
            'order_qty' => ['nullable', 'numeric', 'min:0'],
            'reorder_point' => ['nullable', 'numeric', 'min:0'],
            'last_unit_cost' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $branch = FoodBranch::query()->where('user_id', $user->id)->findOrFail($validated['food_branch_id']);

        $code = filled($validated['code'] ?? null) ? trim((string) $validated['code']) : null;
        if ($code !== null) {
            $exists = FoodMaterial::query()
                ->where('user_id', $user->id)
                ->where('code', $code)
                ->where('id', '!=', $nguyenLieu->id)
                ->exists();
            if ($exists) {
                return back()->withInput()->with('error', 'Mã nguyên liệu đã tồn tại.');
            }
        }

        $orderQty = isset($validated['order_qty']) && $validated['order_qty'] !== null && $validated['order_qty'] !== ''
            ? (float) $validated['order_qty']
            : null;
        if ($orderQty !== null && $orderQty <= 0) {
            $orderQty = null;
        }

        DB::transaction(function () use ($nguyenLieu, $validated, $code, $branch, $orderQty, $request) {
            $nguyenLieu->fill([
                'code' => $code,
                'name' => trim($validated['name']),
                'type' => $validated['type'],
                'unit' => trim($validated['unit']),
                'order_qty' => $orderQty,
                'last_unit_cost' => isset($validated['last_unit_cost'])
                    ? (int) round((float) $validated['last_unit_cost'])
                    : null,
                'note' => $validated['note'] ?? null,
                'active' => $request->boolean('active', true),
            ]);
            $nguyenLieu->save();

            $stock = FoodMaterialStock::forMaterialBranch($nguyenLieu->id, $branch->id);
            $stock->reorder_point = (float) ($validated['reorder_point'] ?? 0);
            $stock->save();
        });

        return redirect()
            ->route('food.nguyen-lieu', ['branch_id' => $branch->id])
            ->with('success', 'Đã cập nhật.');
    }

    public function destroy(Request $request, FoodMaterial $nguyenLieu): RedirectResponse
    {
        $user = $request->user();
        if (! $user || (int) $nguyenLieu->user_id !== (int) $user->id) {
            abort(403);
        }
        $branchId = $request->input('branch_id');
        $nguyenLieu->delete();

        return redirect()
            ->route('food.nguyen-lieu', array_filter(['branch_id' => $branchId]))
            ->with('success', 'Đã xóa nguyên liệu/bao bì.');
    }

    public function stockIn(Request $request, FoodMaterial $nguyenLieu): RedirectResponse
    {
        return $this->applyStockMovement($request, $nguyenLieu, FoodMaterialStockMovement::TYPE_IN);
    }

    public function stockOut(Request $request, FoodMaterial $nguyenLieu): RedirectResponse
    {
        return $this->applyStockMovement($request, $nguyenLieu, FoodMaterialStockMovement::TYPE_OUT);
    }

    public function stockAdjust(Request $request, FoodMaterial $nguyenLieu): RedirectResponse
    {
        $user = $request->user();
        if (! $user || (int) $nguyenLieu->user_id !== (int) $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'food_branch_id' => ['required', 'integer', 'exists:food_branches,id'],
            'stock_on_hand' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $branch = FoodBranch::query()->where('user_id', $user->id)->findOrFail($validated['food_branch_id']);
        $newStock = round((float) $validated['stock_on_hand'], 4);

        DB::transaction(function () use ($nguyenLieu, $user, $branch, $newStock, $validated) {
            $stock = FoodMaterialStock::query()
                ->where('food_material_id', $nguyenLieu->id)
                ->where('food_branch_id', $branch->id)
                ->lockForUpdate()
                ->first();
            if (! $stock) {
                $stock = FoodMaterialStock::forMaterialBranch($nguyenLieu->id, $branch->id);
                $stock = FoodMaterialStock::query()->whereKey($stock->id)->lockForUpdate()->first();
            }

            $old = (float) $stock->stock_on_hand;
            $delta = abs($newStock - $old);
            $stock->stock_on_hand = $newStock;
            $stock->save();

            FoodMaterialStockMovement::query()->create([
                'food_material_id' => $nguyenLieu->id,
                'user_id' => $user->id,
                'food_branch_id' => $branch->id,
                'type' => FoodMaterialStockMovement::TYPE_ADJUST,
                'qty' => $delta > 0 ? $delta : 0,
                'stock_after' => $newStock,
                'note' => $validated['note'] ?? ('Điều chỉnh tồn '.$branch->name.': '.$old.' → '.$newStock),
            ]);
        });

        return redirect()
            ->route('food.nguyen-lieu', ['branch_id' => $branch->id])
            ->with('success', 'Đã điều chỉnh tồn kho.');
    }

    private function applyStockMovement(Request $request, FoodMaterial $material, string $type): RedirectResponse
    {
        $user = $request->user();
        if (! $user || (int) $material->user_id !== (int) $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'food_branch_id' => ['required', 'integer', 'exists:food_branches,id'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:500'],
            'last_unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $branch = FoodBranch::query()->where('user_id', $user->id)->findOrFail($validated['food_branch_id']);
        $qty = round((float) $validated['qty'], 4);

        $error = null;
        DB::transaction(function () use ($material, $user, $branch, $type, $qty, $validated, &$error) {
            $stock = FoodMaterialStock::query()
                ->where('food_material_id', $material->id)
                ->where('food_branch_id', $branch->id)
                ->lockForUpdate()
                ->first();
            if (! $stock) {
                $stock = FoodMaterialStock::forMaterialBranch($material->id, $branch->id);
                $stock = FoodMaterialStock::query()->whereKey($stock->id)->lockForUpdate()->first();
            }

            $current = (float) $stock->stock_on_hand;
            if ($type === FoodMaterialStockMovement::TYPE_OUT && $qty > $current) {
                $error = 'Số lượng xuất vượt tồn '.$branch->name.' ('.$current.').';

                return;
            }

            $after = $type === FoodMaterialStockMovement::TYPE_IN
                ? $current + $qty
                : $current - $qty;
            $stock->stock_on_hand = round($after, 4);
            $stock->save();

            if ($type === FoodMaterialStockMovement::TYPE_IN && isset($validated['last_unit_cost'])) {
                $material->last_unit_cost = (int) round((float) $validated['last_unit_cost']);
                $material->save();
            }

            FoodMaterialStockMovement::query()->create([
                'food_material_id' => $material->id,
                'user_id' => $user->id,
                'food_branch_id' => $branch->id,
                'type' => $type,
                'qty' => $qty,
                'stock_after' => $stock->stock_on_hand,
                'note' => $validated['note'] ?? null,
            ]);
        });

        if ($error) {
            return back()->with('error', $error);
        }

        $msg = $type === FoodMaterialStockMovement::TYPE_IN ? 'Đã nhập kho.' : 'Đã xuất kho.';

        return redirect()
            ->route('food.nguyen-lieu', ['branch_id' => $branch->id])
            ->with('success', $msg);
    }

    public function datHang(Request $request, MaterialConsumptionService $service): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $branches = FoodBranch::query()->where('user_id', $user->id)->orderBy('name')->get();
        if ($branches->isEmpty()) {
            return view('pages.food.nguyen-lieu.dat-hang', [
                'title' => 'Gợi ý đặt hàng NL/bao bì',
                'from' => now()->subDays(13)->startOfDay(),
                'to' => now()->endOfDay(),
                'rows' => collect(),
                'needOrderRows' => collect(),
                'branches' => $branches,
                'branch' => null,
                'typeLabels' => FoodMaterial::typeLabels(),
            ]);
        }

        $branch = $this->resolveBranch($request, $branches);

        $from = $request->filled('from_date')
            ? Carbon::parse($request->input('from_date'))->startOfDay()
            : now()->subDays(13)->startOfDay();
        $to = $request->filled('to_date')
            ? Carbon::parse($request->input('to_date'))->endOfDay()
            : now()->endOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $rows = $service->summarizeForUser($user->id, $from, $to, $branch->id);
        $needOrderRows = $rows->filter(fn ($r) => $r['need_order'] > 0 || $r['below_reorder'])->values();

        return view('pages.food.nguyen-lieu.dat-hang', [
            'title' => 'Gợi ý đặt hàng NL/bao bì',
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'needOrderRows' => $needOrderRows,
            'branches' => $branches,
            'branch' => $branch,
            'typeLabels' => FoodMaterial::typeLabels(),
        ]);
    }

    /** @param  \Illuminate\Support\Collection<int, FoodBranch>  $branches */
    private function resolveBranch(Request $request, $branches): FoodBranch
    {
        $branchId = (int) $request->input('branch_id', 0);
        $branch = $branches->firstWhere('id', $branchId);
        if ($branch) {
            return $branch;
        }

        return $branches->first();
    }

    private function ensureStocksForBranch(int $userId, int $branchId): void
    {
        $materialIds = FoodMaterial::query()->where('user_id', $userId)->pluck('id');
        foreach ($materialIds as $mid) {
            FoodMaterialStock::forMaterialBranch((int) $mid, $branchId);
        }
    }

    public function congThucIndex(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $sessionKey = 'food_cong_thuc_search';

        if ($request->boolean('clear_search')) {
            $request->session()->forget($sessionKey);

            return redirect()->route('food.cong-thuc');
        }

        if ($request->has('q')) {
            $q = trim((string) $request->input('q', ''));
            if ($q === '') {
                $request->session()->forget($sessionKey);
            } else {
                $request->session()->put($sessionKey, $q);
            }

            // Giữ URL sạch sau khi lưu session (tránh mất khi chuyển trang)
            if ($request->query('q') !== null && ! $request->ajax()) {
                return redirect()->route('food.cong-thuc');
            }
        }

        $search = trim((string) $request->session()->get($sessionKey, ''));

        $templates = FoodRecipeTemplate::query()
            ->where('user_id', $user->id)
            ->withCount(['items', 'products'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->orderBy('name')
            ->get();

        return view('pages.food.nguyen-lieu.cong-thuc-index', [
            'title' => 'Công thức định lượng',
            'templates' => $templates,
            'search' => $search,
        ]);
    }

    public function congThucStore(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $template = FoodRecipeTemplate::query()->create([
            'user_id' => $user->id,
            'name' => trim($validated['name']),
            'note' => $validated['note'] ?? null,
        ]);

        return redirect()
            ->route('food.cong-thuc.show', $template)
            ->with('success', 'Đã tạo công thức. Thêm định lượng và gán sản phẩm.');
    }

    public function congThucShow(Request $request, FoodRecipeTemplate $congThuc): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user || (int) $congThuc->user_id !== (int) $user->id) {
            abort(403);
        }

        $congThuc->load(['items.material', 'items.childTemplate', 'products']);
        $materials = FoodMaterial::query()
            ->where('user_id', $user->id)
            ->where('active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get();
        $otherTemplates = FoodRecipeTemplate::query()
            ->where('user_id', $user->id)
            ->where('id', '!=', $congThuc->id)
            ->orderBy('name')
            ->get();
        $products = FoodProduct::query()
            ->with('recipeTemplate:id,name')
            ->where('user_id', $user->id)
            ->orderBy('ma_hang')
            ->get();
        $assignedIds = $congThuc->products->pluck('id')->all();

        $bom = app(\App\Services\Food\RecipeBomService::class);
        $itemsByTemplate = $bom->itemsGroupedForUser((int) $user->id);

        $bomPreview = $this->formatBomRows(
            $this->expandTemplateQty($bom, $itemsByTemplate, (int) $congThuc->id, 1.0)
        );

        // Mỗi dòng CT con → danh sách NL gốc đã nhân hệ số
        $nestedByItemId = [];
        foreach ($congThuc->items as $item) {
            if (! $item->isRecipeLine() || ! $item->child_template_id) {
                continue;
            }
            $nestedByItemId[$item->id] = $this->formatBomRows(
                $this->expandTemplateQty(
                    $bom,
                    $itemsByTemplate,
                    (int) $item->child_template_id,
                    (float) $item->qty_per_unit
                )
            );
        }

        return view('pages.food.nguyen-lieu.cong-thuc-show', [
            'title' => 'Công thức: '.$congThuc->name,
            'template' => $congThuc,
            'materials' => $materials,
            'otherTemplates' => $otherTemplates,
            'products' => $products,
            'assignedIds' => $assignedIds,
            'typeLabels' => FoodMaterial::typeLabels(),
            'bomPreview' => $bomPreview,
            'nestedByItemId' => $nestedByItemId,
        ]);
    }

    /**
     * @param  Collection<int, Collection<int, FoodRecipeTemplateItem>>  $itemsByTemplate
     * @return array<int, float>
     */
    private function expandTemplateQty(
        \App\Services\Food\RecipeBomService $bom,
        Collection $itemsByTemplate,
        int $templateId,
        float $multiplier
    ): array {
        $consumed = [];
        $via = null;
        $bom->accumulate($templateId, $multiplier, $itemsByTemplate, $consumed, $via);

        return $consumed;
    }

    /**
     * @param  array<int, float>  $consumed
     * @return list<array{name: string, unit: string, qty: float, type: string}>
     */
    private function formatBomRows(array $consumed): array
    {
        if ($consumed === []) {
            return [];
        }
        $mats = FoodMaterial::query()->whereIn('id', array_keys($consumed))->get()->keyBy('id');
        $rows = [];
        foreach ($consumed as $mid => $qty) {
            $m = $mats->get($mid);
            if (! $m || $qty <= 0) {
                continue;
            }
            $rows[] = [
                'name' => $m->name,
                'unit' => $m->unit,
                'qty' => (float) $qty,
                'type' => $m->type,
            ];
        }
        usort($rows, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $rows;
    }

    public function congThucUpdate(Request $request, FoodRecipeTemplate $congThuc): RedirectResponse
    {
        $user = $request->user();
        if (! $user || (int) $congThuc->user_id !== (int) $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $congThuc->update([
            'name' => trim($validated['name']),
            'note' => $validated['note'] ?? null,
        ]);

        return redirect()->route('food.cong-thuc.show', $congThuc)->with('success', 'Đã cập nhật công thức.');
    }

    public function congThucDestroy(Request $request, FoodRecipeTemplate $congThuc): RedirectResponse
    {
        $user = $request->user();
        if (! $user || (int) $congThuc->user_id !== (int) $user->id) {
            abort(403);
        }
        $congThuc->delete();

        return redirect()->route('food.cong-thuc')->with('success', 'Đã xóa công thức.');
    }

    public function congThucDuplicate(Request $request, FoodRecipeTemplate $congThuc): RedirectResponse
    {
        $user = $request->user();
        if (! $user || (int) $congThuc->user_id !== (int) $user->id) {
            abort(403);
        }

        $congThuc->load('items');

        $copy = DB::transaction(function () use ($user, $congThuc) {
            $copy = FoodRecipeTemplate::query()->create([
                'user_id' => $user->id,
                'name' => $congThuc->name.' (bản sao)',
                'note' => $congThuc->note,
            ]);

            foreach ($congThuc->items as $item) {
                FoodRecipeTemplateItem::query()->create([
                    'food_recipe_template_id' => $copy->id,
                    'item_type' => $item->item_type ?? FoodRecipeTemplateItem::TYPE_MATERIAL,
                    'food_material_id' => $item->food_material_id,
                    'child_template_id' => $item->child_template_id,
                    'qty_per_unit' => $item->qty_per_unit,
                ]);
            }

            return $copy;
        });

        return redirect()
            ->route('food.cong-thuc.show', $copy)
            ->with('success', 'Đã sao chép công thức. Đổi tên / sửa định lượng rồi gán sản phẩm.');
    }

    public function congThucStoreItem(Request $request, FoodRecipeTemplate $congThuc): RedirectResponse
    {
        $user = $request->user();
        if (! $user || (int) $congThuc->user_id !== (int) $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'item_type' => ['required', 'in:material,recipe'],
            'food_material_id' => ['nullable', 'integer', 'exists:food_materials,id'],
            'child_template_id' => ['nullable', 'integer', 'exists:food_recipe_templates,id'],
            'qty_per_unit' => ['required', 'numeric', 'gt:0'],
        ]);

        $qty = round((float) $validated['qty_per_unit'], 6);
        $bom = app(\App\Services\Food\RecipeBomService::class);

        if ($validated['item_type'] === FoodRecipeTemplateItem::TYPE_RECIPE) {
            $childId = (int) ($validated['child_template_id'] ?? 0);
            if ($childId <= 0) {
                return back()->withErrors(['child_template_id' => 'Chọn công thức con.'])->withInput();
            }
            $child = FoodRecipeTemplate::query()->where('user_id', $user->id)->findOrFail($childId);
            if ($bom->wouldCreateCycle((int) $congThuc->id, (int) $child->id, (int) $user->id)) {
                return back()->withErrors(['child_template_id' => 'Không thể gắn: tạo vòng lặp công thức.'])->withInput();
            }

            FoodRecipeTemplateItem::query()->updateOrCreate(
                [
                    'food_recipe_template_id' => $congThuc->id,
                    'child_template_id' => $child->id,
                ],
                [
                    'item_type' => FoodRecipeTemplateItem::TYPE_RECIPE,
                    'food_material_id' => null,
                    'qty_per_unit' => $qty,
                ]
            );
        } else {
            $materialId = (int) ($validated['food_material_id'] ?? 0);
            if ($materialId <= 0) {
                return back()->withErrors(['food_material_id' => 'Chọn nguyên liệu.'])->withInput();
            }
            $material = FoodMaterial::query()->where('user_id', $user->id)->findOrFail($materialId);

            FoodRecipeTemplateItem::query()->updateOrCreate(
                [
                    'food_recipe_template_id' => $congThuc->id,
                    'food_material_id' => $material->id,
                ],
                [
                    'item_type' => FoodRecipeTemplateItem::TYPE_MATERIAL,
                    'child_template_id' => null,
                    'qty_per_unit' => $qty,
                ]
            );
        }

        return redirect()->route('food.cong-thuc.show', $congThuc)->with('success', 'Đã lưu định lượng.');
    }

    public function congThucDestroyItem(Request $request, FoodRecipeTemplate $congThuc, FoodRecipeTemplateItem $item): RedirectResponse
    {
        $user = $request->user();
        if (! $user || (int) $congThuc->user_id !== (int) $user->id) {
            abort(403);
        }
        if ((int) $item->food_recipe_template_id !== (int) $congThuc->id) {
            abort(404);
        }
        $item->delete();

        return redirect()->route('food.cong-thuc.show', $congThuc)->with('success', 'Đã xóa dòng định lượng.');
    }

    public function congThucSyncProducts(Request $request, FoodRecipeTemplate $congThuc): RedirectResponse
    {
        $user = $request->user();
        if (! $user || (int) $congThuc->user_id !== (int) $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer'],
        ]);

        $ids = collect($validated['product_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $validIds = FoodProduct::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $ids)
            ->pluck('id');

        FoodProduct::query()
            ->where('user_id', $user->id)
            ->where('food_recipe_template_id', $congThuc->id)
            ->whereNotIn('id', $validIds)
            ->update(['food_recipe_template_id' => null]);

        if ($validIds->isNotEmpty()) {
            FoodProduct::query()
                ->where('user_id', $user->id)
                ->whereIn('id', $validIds)
                ->update(['food_recipe_template_id' => $congThuc->id]);
        }

        return redirect()
            ->route('food.cong-thuc.show', $congThuc)
            ->with('success', 'Đã cập nhật danh sách sản phẩm dùng công thức.');
    }

    public function productRecipe(Request $request, int $id): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $product = FoodProduct::query()->where('user_id', $user->id)->with('recipeTemplate')->findOrFail($id);
        $templates = FoodRecipeTemplate::query()->where('user_id', $user->id)->orderBy('name')->get();

        return view('pages.food.nguyen-lieu.cong-thuc-assign', [
            'title' => 'Công thức: '.$product->ten_hang,
            'product' => $product,
            'templates' => $templates,
        ]);
    }

    public function assignProductTemplate(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $product = FoodProduct::query()->where('user_id', $user->id)->findOrFail($id);
        $validated = $request->validate([
            'food_recipe_template_id' => ['nullable', 'integer', 'exists:food_recipe_templates,id'],
        ]);

        $tplId = $validated['food_recipe_template_id'] ?? null;
        if ($tplId) {
            FoodRecipeTemplate::query()->where('user_id', $user->id)->findOrFail($tplId);
        }

        $product->food_recipe_template_id = $tplId;
        $product->save();

        return redirect()
            ->route('food.san-pham.cong-thuc', $product->id)
            ->with('success', $tplId ? 'Đã gán công thức.' : 'Đã gỡ công thức.');
    }

    public function storeProductRecipe(Request $request, int $id): RedirectResponse
    {
        return redirect()->route('food.san-pham.cong-thuc', $id)
            ->with('error', 'Dùng công thức mẫu (menu Công thức) rồi gán cho sản phẩm.');
    }

    public function destroyProductRecipe(Request $request, int $id, FoodProductRecipe $recipe): RedirectResponse
    {
        return redirect()->route('food.cong-thuc');
    }

    /**
     * Map material_id => [{label, qty, kind, product_id?}] — món / CT dùng NL (đã bung BOM lồng).
     *
     * @param  Collection<int, int|string>  $materialIds
     * @return array<int, list<array{label: string, qty: float, kind: string, product_id?: int}>>
     */
    private function buildMaterialUsages(int $userId, Collection $materialIds): array
    {
        $ids = $materialIds->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $result = [];
        foreach ($ids as $id) {
            $result[$id] = [];
        }
        if ($ids->isEmpty()) {
            return $result;
        }

        $bom = app(\App\Services\Food\RecipeBomService::class);
        $templates = FoodRecipeTemplate::query()
            ->where('user_id', $userId)
            ->with(['products' => fn ($q) => $q->select('id', 'ten_hang', 'ma_hang', 'food_recipe_template_id')->orderBy('ten_hang')])
            ->get()
            ->keyBy('id');

        foreach ($ids as $mid) {
            $tplIds = $bom->templateIdsUsingMaterial($userId, $mid);
            foreach ($tplIds as $tplId) {
                $tpl = $templates->get($tplId);
                if (! $tpl) {
                    continue;
                }
                $qty = $bom->materialQtyInTemplate($userId, $tplId, $mid);
                if ($qty <= 0) {
                    continue;
                }
                $products = $tpl->products;
                if ($products->isEmpty()) {
                    $result[$mid][] = [
                        'label' => 'CT: '.$tpl->name,
                        'qty' => $qty,
                        'kind' => 'template',
                    ];

                    continue;
                }
                foreach ($products as $product) {
                    $result[$mid][] = [
                        'label' => trim((string) ($product->ten_hang ?: $product->ma_hang)) ?: 'SP #'.$product->id,
                        'qty' => $qty,
                        'kind' => 'product',
                        'product_id' => (int) $product->id,
                    ];
                }
            }
        }

        $legacy = FoodProductRecipe::query()
            ->whereIn('food_material_id', $ids)
            ->whereHas('product', fn ($q) => $q->where('user_id', $userId))
            ->with(['product:id,ten_hang,ma_hang,user_id'])
            ->get();

        foreach ($legacy as $row) {
            $mid = (int) $row->food_material_id;
            $pid = (int) $row->food_product_id;
            $already = collect($result[$mid] ?? [])->contains(
                fn (array $u) => ($u['product_id'] ?? null) === $pid
            );
            if ($already) {
                continue;
            }
            $product = $row->product;
            $result[$mid][] = [
                'label' => trim((string) ($product?->ten_hang ?: $product?->ma_hang)) ?: 'SP #'.$pid,
                'qty' => (float) $row->qty_per_unit,
                'kind' => 'product',
                'product_id' => $pid,
            ];
        }

        foreach ($result as &$rows) {
            usort($rows, static fn (array $a, array $b) => strcmp($a['label'], $b['label']));
        }
        unset($rows);

        return $result;
    }
}
