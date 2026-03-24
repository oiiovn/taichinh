<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_report_debts', function (Blueprint $table) {
            if (! Schema::hasColumn('food_report_debts', 'addition_amount')) {
                $table->decimal('addition_amount', 15, 0)->default(0)->after('deduction_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('food_report_debts', function (Blueprint $table) {
            if (Schema::hasColumn('food_report_debts', 'addition_amount')) {
                $table->dropColumn('addition_amount');
            }
        });
    }
};
