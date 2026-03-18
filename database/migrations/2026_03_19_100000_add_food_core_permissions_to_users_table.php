<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_manage_food_tong_quan')->default(false)->after('can_manage_food_luong');
            $table->boolean('can_manage_food_doanh_so')->default(false)->after('can_manage_food_tong_quan');
            $table->boolean('can_manage_food_san_pham')->default(false)->after('can_manage_food_doanh_so');
            $table->boolean('can_manage_food_bao_cao')->default(false)->after('can_manage_food_san_pham');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'can_manage_food_tong_quan',
                'can_manage_food_doanh_so',
                'can_manage_food_san_pham',
                'can_manage_food_bao_cao',
            ]);
        });
    }
};
