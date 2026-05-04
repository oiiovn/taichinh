<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_buff_order_schedules', function (Blueprint $table) {
            if (! Schema::hasColumn('food_buff_order_schedules', 'order_channel')) {
                $table->string('order_channel', 30)
                    ->default('WEB')
                    ->after('branch_targets');
            }
        });
    }

    public function down(): void
    {
        Schema::table('food_buff_order_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('food_buff_order_schedules', 'order_channel')) {
                $table->dropColumn('order_channel');
            }
        });
    }
};
