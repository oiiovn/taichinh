<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_sales_report_items', function (Blueprint $table) {
            $table->decimal('gia_von_unit', 15, 4)->unsigned()->default(0)->after('gia_tri_ban_chi_tiet');
        });
    }

    public function down(): void
    {
        Schema::table('food_sales_report_items', function (Blueprint $table) {
            $table->dropColumn('gia_von_unit');
        });
    }
};
