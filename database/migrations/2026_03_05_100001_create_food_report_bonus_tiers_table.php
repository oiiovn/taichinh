<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_report_bonus_tiers', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_total_cost', 15, 0)->unsigned()->comment('Tổng vốn tối thiểu (VNĐ)');
            $table->decimal('bonus_amount', 15, 0)->unsigned()->comment('Thưởng áp dụng (VNĐ)');
            $table->unsignedTinyInteger('sort_order')->default(0)->comment('Thứ tự: cao hơn = ưu tiên (600k trước 400k)');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_report_bonus_tiers');
    }
};
