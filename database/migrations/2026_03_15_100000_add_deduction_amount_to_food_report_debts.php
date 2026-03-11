<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_report_debts', function (Blueprint $table) {
            $table->decimal('deduction_amount', 15, 0)->default(0)->after('only_tien_cong')->comment('Số tiền trừ công nợ (VND); tổng quyết toán - deduction = công nợ thực');
        });
    }

    public function down(): void
    {
        Schema::table('food_report_debts', function (Blueprint $table) {
            $table->dropColumn('deduction_amount');
        });
    }
};
