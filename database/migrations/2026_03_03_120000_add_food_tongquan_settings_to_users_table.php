<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('food_tongquan_settings')->nullable()->after('plan_expires_at')->comment('Bộ lọc Tổng quan Food: period, from_date, to_date, thu_category_ids, chi_category_ids');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('food_tongquan_settings');
        });
    }
};
