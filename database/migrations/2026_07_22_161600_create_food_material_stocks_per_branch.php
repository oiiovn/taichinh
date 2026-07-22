<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('food_material_stocks')) {
            Schema::create('food_material_stocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('food_material_id')->constrained('food_materials')->cascadeOnDelete();
                $table->foreignId('food_branch_id')->constrained('food_branches')->cascadeOnDelete();
                $table->decimal('stock_on_hand', 15, 4)->default(0);
                $table->decimal('reorder_point', 15, 4)->default(0);
                $table->timestamps();

                $table->unique(['food_material_id', 'food_branch_id']);
                $table->index(['food_branch_id', 'stock_on_hand'], 'fm_stocks_branch_stock_idx');
            });
        }

        if (! Schema::hasColumn('food_material_stock_movements', 'food_branch_id')) {
            Schema::table('food_material_stock_movements', function (Blueprint $table) {
                $table->foreignId('food_branch_id')
                    ->nullable()
                    ->after('food_sales_report_id')
                    ->constrained('food_branches')
                    ->nullOnDelete();
            });
        }

        $indexExists = collect(DB::select('SHOW INDEX FROM food_material_stock_movements'))
            ->contains(fn ($row) => ($row->Key_name ?? '') === 'fm_movements_branch_material_idx');
        if (! $indexExists) {
            Schema::table('food_material_stock_movements', function (Blueprint $table) {
                $table->index(['food_branch_id', 'food_material_id'], 'fm_movements_branch_material_idx');
            });
        }

        if (Schema::hasColumn('food_materials', 'stock_on_hand') && DB::table('food_material_stocks')->count() === 0) {
            $this->migrateExistingStock();
        } else {
            $this->backfillMovementBranches();
        }

        if (Schema::hasColumn('food_materials', 'stock_on_hand')) {
            Schema::table('food_materials', function (Blueprint $table) {
                $table->dropColumn(['stock_on_hand', 'reorder_point']);
            });
        }
    }

    private function migrateExistingStock(): void
    {
        $materials = DB::table('food_materials')->get();
        if ($materials->isEmpty()) {
            return;
        }

        foreach ($materials->groupBy('user_id') as $userId => $userMaterials) {
            $branches = DB::table('food_branches')->where('user_id', $userId)->orderBy('id')->get();
            if ($branches->isEmpty()) {
                continue;
            }

            $primaryBranchId = $this->resolvePrimaryBranchId((int) $userId, $branches->pluck('id')->all());

            foreach ($userMaterials as $material) {
                foreach ($branches as $branch) {
                    $isPrimary = (int) $branch->id === $primaryBranchId;
                    DB::table('food_material_stocks')->insert([
                        'food_material_id' => $material->id,
                        'food_branch_id' => $branch->id,
                        'stock_on_hand' => $isPrimary ? (float) $material->stock_on_hand : 0,
                        'reorder_point' => (float) $material->reorder_point,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $this->backfillMovementBranchesForUser((int) $userId, $primaryBranchId);
        }
    }

    private function backfillMovementBranches(): void
    {
        $userIds = DB::table('food_material_stock_movements')
            ->whereNull('food_branch_id')
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $branchIds = DB::table('food_branches')->where('user_id', $userId)->orderBy('id')->pluck('id')->all();
            if ($branchIds === []) {
                continue;
            }
            $primary = $this->resolvePrimaryBranchId((int) $userId, $branchIds);
            $this->backfillMovementBranchesForUser((int) $userId, $primary);
        }
    }

    private function backfillMovementBranchesForUser(int $userId, int $primaryBranchId): void
    {
        $movements = DB::table('food_material_stock_movements')
            ->where('user_id', $userId)
            ->whereNull('food_branch_id')
            ->get(['id', 'food_sales_report_id']);

        foreach ($movements as $mov) {
            $branchId = $primaryBranchId;
            if ($mov->food_sales_report_id) {
                $reportBranch = DB::table('food_sales_reports')
                    ->where('id', $mov->food_sales_report_id)
                    ->value('food_branch_id');
                if ($reportBranch) {
                    $branchId = (int) $reportBranch;
                }
            }
            DB::table('food_material_stock_movements')
                ->where('id', $mov->id)
                ->update(['food_branch_id' => $branchId]);
        }
    }

    /** @param  list<int>  $branchIds */
    private function resolvePrimaryBranchId(int $userId, array $branchIds): int
    {
        $fromReport = DB::table('food_sales_reports')
            ->where('user_id', $userId)
            ->whereNotNull('food_branch_id')
            ->whereIn('food_branch_id', $branchIds)
            ->orderByDesc('report_date')
            ->orderByDesc('id')
            ->value('food_branch_id');

        if ($fromReport) {
            return (int) $fromReport;
        }

        return (int) $branchIds[0];
    }

    public function down(): void
    {
        if (! Schema::hasColumn('food_materials', 'stock_on_hand')) {
            Schema::table('food_materials', function (Blueprint $table) {
                $table->decimal('stock_on_hand', 15, 4)->default(0)->after('unit');
                $table->decimal('reorder_point', 15, 4)->default(0)->after('stock_on_hand');
            });
        }

        if (Schema::hasTable('food_material_stocks')) {
            $stocks = DB::table('food_material_stocks')
                ->select('food_material_id', DB::raw('SUM(stock_on_hand) as stock_sum'), DB::raw('MAX(reorder_point) as rp'))
                ->groupBy('food_material_id')
                ->get();

            foreach ($stocks as $row) {
                DB::table('food_materials')->where('id', $row->food_material_id)->update([
                    'stock_on_hand' => $row->stock_sum,
                    'reorder_point' => $row->rp,
                ]);
            }
        }

        $indexExists = collect(DB::select('SHOW INDEX FROM food_material_stock_movements'))
            ->contains(fn ($row) => ($row->Key_name ?? '') === 'fm_movements_branch_material_idx');
        if ($indexExists) {
            Schema::table('food_material_stock_movements', function (Blueprint $table) {
                $table->dropIndex('fm_movements_branch_material_idx');
            });
        }

        if (Schema::hasColumn('food_material_stock_movements', 'food_branch_id')) {
            Schema::table('food_material_stock_movements', function (Blueprint $table) {
                $table->dropConstrainedForeignId('food_branch_id');
            });
        }

        Schema::dropIfExists('food_material_stocks');
    }
};
