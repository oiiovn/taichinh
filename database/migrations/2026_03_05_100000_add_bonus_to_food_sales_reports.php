<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_sales_reports', function (Blueprint $table) {
            $table->decimal('bonus', 15, 0)->unsigned()->default(0)->after('total_tien_cong');
        });
    }

    public function down(): void
    {
        Schema::table('food_sales_reports', function (Blueprint $table) {
            $table->dropColumn('bonus');
        });
    }
};
