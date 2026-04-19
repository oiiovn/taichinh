<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_sales_reports', function (Blueprint $table) {
            $table->decimal('phi_buff', 15, 0)->nullable()->after('doanh_so')->comment('Phí buff nhập tay (VND)');
            $table->decimal('phi_ads', 15, 0)->nullable()->after('phi_buff')->comment('Phí ads nhập tay (VND)');
        });
    }

    public function down(): void
    {
        Schema::table('food_sales_reports', function (Blueprint $table) {
            $table->dropColumn(['phi_buff', 'phi_ads']);
        });
    }
};
