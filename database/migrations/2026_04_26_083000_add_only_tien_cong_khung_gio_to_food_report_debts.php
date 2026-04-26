<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_report_debts', function (Blueprint $table) {
            if (! Schema::hasColumn('food_report_debts', 'only_tien_cong_khung_gio')) {
                $table->boolean('only_tien_cong_khung_gio')
                    ->default(false)
                    ->after('only_tien_cong')
                    ->comment('Chỉ tính tiền công đơn trong khung giờ 16:30-22:00');
            }
        });
    }

    public function down(): void
    {
        Schema::table('food_report_debts', function (Blueprint $table) {
            if (Schema::hasColumn('food_report_debts', 'only_tien_cong_khung_gio')) {
                $table->dropColumn('only_tien_cong_khung_gio');
            }
        });
    }
};
