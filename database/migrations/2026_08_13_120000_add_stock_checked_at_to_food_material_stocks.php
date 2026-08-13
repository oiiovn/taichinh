<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('food_material_stocks')) {
            return;
        }

        Schema::table('food_material_stocks', function (Blueprint $table) {
            if (! Schema::hasColumn('food_material_stocks', 'stock_checked_at')) {
                $table->timestamp('stock_checked_at')->nullable()->after('reorder_point');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('food_material_stocks')) {
            return;
        }

        Schema::table('food_material_stocks', function (Blueprint $table) {
            if (Schema::hasColumn('food_material_stocks', 'stock_checked_at')) {
                $table->dropColumn('stock_checked_at');
            }
        });
    }
};
