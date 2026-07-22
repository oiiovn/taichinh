<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_materials', function (Blueprint $table) {
            $table->decimal('order_qty', 15, 4)->nullable()->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('food_materials', function (Blueprint $table) {
            $table->dropColumn('order_qty');
        });
    }
};
