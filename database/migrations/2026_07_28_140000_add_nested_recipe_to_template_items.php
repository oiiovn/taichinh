<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cột mới (idempotent nếu migrate dở)
        if (! Schema::hasColumn('food_recipe_template_items', 'item_type')) {
            Schema::table('food_recipe_template_items', function (Blueprint $table) {
                $table->string('item_type', 16)->default('material')->after('food_recipe_template_id');
            });
        }
        if (! Schema::hasColumn('food_recipe_template_items', 'child_template_id')) {
            Schema::table('food_recipe_template_items', function (Blueprint $table) {
                $table->unsignedBigInteger('child_template_id')->nullable()->after('food_material_id');
            });
        }

        // FK child (nếu chưa có)
        $this->ensureForeignKey('food_recipe_template_items', 'child_template_id', 'food_recipe_templates', 'id', true);

        // Phải drop FK material trước khi drop unique / đổi nullable
        $this->dropForeignIfExists('food_recipe_template_items', 'food_material_id');
        $this->dropIndexIfExists('food_recipe_template_items', 'food_recipe_tpl_item_unique');

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE food_recipe_template_items MODIFY food_material_id BIGINT UNSIGNED NULL');
        }

        $this->ensureForeignKey('food_recipe_template_items', 'food_material_id', 'food_materials', 'id', true);

        $this->dropIndexIfExists('food_recipe_template_items', 'food_recipe_tpl_mat_unique');
        $this->dropIndexIfExists('food_recipe_template_items', 'food_recipe_tpl_child_unique');

        Schema::table('food_recipe_template_items', function (Blueprint $table) {
            $table->unique(['food_recipe_template_id', 'food_material_id'], 'food_recipe_tpl_mat_unique');
            $table->unique(['food_recipe_template_id', 'child_template_id'], 'food_recipe_tpl_child_unique');
        });
    }

    public function down(): void
    {
        $this->dropIndexIfExists('food_recipe_template_items', 'food_recipe_tpl_mat_unique');
        $this->dropIndexIfExists('food_recipe_template_items', 'food_recipe_tpl_child_unique');
        $this->dropForeignIfExists('food_recipe_template_items', 'child_template_id');
        $this->dropForeignIfExists('food_recipe_template_items', 'food_material_id');

        Schema::table('food_recipe_template_items', function (Blueprint $table) {
            if (Schema::hasColumn('food_recipe_template_items', 'item_type')) {
                $table->dropColumn('item_type');
            }
            if (Schema::hasColumn('food_recipe_template_items', 'child_template_id')) {
                $table->dropColumn('child_template_id');
            }
        });

        DB::table('food_recipe_template_items')->whereNull('food_material_id')->delete();

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE food_recipe_template_items MODIFY food_material_id BIGINT UNSIGNED NOT NULL');
        }

        Schema::table('food_recipe_template_items', function (Blueprint $table) {
            $table->foreign('food_material_id')
                ->references('id')
                ->on('food_materials')
                ->cascadeOnDelete();
            $table->unique(['food_recipe_template_id', 'food_material_id'], 'food_recipe_tpl_item_unique');
        });
    }

    private function dropForeignIfExists(string $table, string $column): void
    {
        $sm = Schema::getConnection()->getSchemaBuilder();
        // Laravel: dropForeign by column array
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
            });
        } catch (\Throwable) {
            // ignore
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($index) {
                $blueprint->dropUnique($index);
            });
        } catch (\Throwable) {
            try {
                Schema::table($table, function (Blueprint $blueprint) use ($index) {
                    $blueprint->dropIndex($index);
                });
            } catch (\Throwable) {
                // ignore
            }
        }
    }

    private function ensureForeignKey(string $table, string $column, string $refTable, string $refColumn, bool $cascade): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $refTable, $refColumn, $cascade) {
                $fk = $blueprint->foreign($column)->references($refColumn)->on($refTable);
                if ($cascade) {
                    $fk->cascadeOnDelete();
                }
            });
        } catch (\Throwable) {
            // already exists
        }
    }
};
