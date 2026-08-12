<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_branches', function (Blueprint $table) {
            $table->boolean('include_labor_in_settlement')
                ->default(true)
                ->after('check_in_radius_meters')
                ->comment('false = quyết toán báo cáo bán hàng không cộng tiền công');
        });

        DB::table('food_branches')
            ->where(function ($q) {
                $q->where('name', 'like', '%Lê Văn Quới%')
                    ->orWhere('name', 'like', '%Tan Son%')
                    ->orWhere('name', 'like', '%Tân Sơn%');
            })
            ->update(['include_labor_in_settlement' => false]);
    }

    public function down(): void
    {
        Schema::table('food_branches', function (Blueprint $table) {
            $table->dropColumn('include_labor_in_settlement');
        });
    }
};
