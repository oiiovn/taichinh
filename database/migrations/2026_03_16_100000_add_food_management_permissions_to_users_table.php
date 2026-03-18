<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_manage_food_cham_cong')->default(false)->after('can_manage_food_employees');
            $table->boolean('can_manage_food_xin_nghi')->default(false)->after('can_manage_food_cham_cong');
            $table->boolean('can_manage_food_ung_luong')->default(false)->after('can_manage_food_xin_nghi');
            $table->boolean('can_manage_food_luong')->default(false)->after('can_manage_food_ung_luong');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'can_manage_food_cham_cong',
                'can_manage_food_xin_nghi',
                'can_manage_food_ung_luong',
                'can_manage_food_luong',
            ]);
        });
    }
};
