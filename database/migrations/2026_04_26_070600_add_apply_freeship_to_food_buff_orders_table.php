<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_buff_orders', function (Blueprint $table) {
            $table->boolean('apply_freeship')->default(false)->after('product_name');
        });
    }

    public function down(): void
    {
        Schema::table('food_buff_orders', function (Blueprint $table) {
            $table->dropColumn('apply_freeship');
        });
    }
};
