<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_buff_orders', function (Blueprint $table) {
            $table->string('product_name', 255)->nullable()->after('customer_name');
        });

        DB::table('food_buff_orders')
            ->whereNull('product_name')
            ->update(['product_name' => 'Quán Ship Bù']);
    }

    public function down(): void
    {
        Schema::table('food_buff_orders', function (Blueprint $table) {
            $table->dropColumn('product_name');
        });
    }
};
