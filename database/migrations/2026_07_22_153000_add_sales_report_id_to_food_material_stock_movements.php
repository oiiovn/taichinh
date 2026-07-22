<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_material_stock_movements', function (Blueprint $table) {
            $table->foreignId('food_sales_report_id')
                ->nullable()
                ->after('user_id')
                ->constrained('food_sales_reports')
                ->nullOnDelete();
            $table->index(['food_sales_report_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('food_material_stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('food_sales_report_id');
        });
    }
};
