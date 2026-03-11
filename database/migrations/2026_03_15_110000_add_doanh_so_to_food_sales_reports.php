<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_sales_reports', function (Blueprint $table) {
            $table->decimal('doanh_so', 15, 0)->nullable()->after('bonus')->comment('Doanh số (VND) nhập tay; lợi nhuận = doanh_so - quyet_toan');
        });
    }

    public function down(): void
    {
        Schema::table('food_sales_reports', function (Blueprint $table) {
            $table->dropColumn('doanh_so');
        });
    }
};
