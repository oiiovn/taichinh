<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_sales_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_sales_report_id')->constrained('food_sales_reports')->cascadeOnDelete();
            $table->string('nhom_hang', 100)->nullable();
            $table->string('ma_hang', 100)->nullable();
            $table->string('ten_hang')->nullable();
            $table->string('don_vi_tinh', 50)->nullable();
            $table->decimal('sl_ban', 12, 2)->default(0);
            $table->decimal('gia_tri_niem_yet', 15, 2)->default(0);
            $table->decimal('doanh_thu', 15, 2)->default(0);
            $table->decimal('chenh_lech', 15, 2)->default(0);
            $table->decimal('sl_tra', 12, 2)->default(0);
            $table->decimal('gia_tri_tra', 15, 2)->default(0);
            $table->decimal('doanh_thu_thuan', 15, 2)->default(0);
            $table->string('ma_hoa_don', 50)->nullable()->index();
            $table->string('thoi_gian', 50)->nullable();
            $table->string('nguoi_nhan_don')->nullable();
            $table->string('khach_hang')->nullable();
            $table->decimal('sl', 12, 2)->default(0);
            $table->decimal('gia_tri_niem_yet_chi_tiet', 15, 2)->default(0);
            $table->decimal('doanh_thu_chi_tiet', 15, 2)->default(0);
            $table->decimal('gia_tri_ban_chi_tiet', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_sales_report_items');
    }
};
