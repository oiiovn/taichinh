<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_report_debts', function (Blueprint $table) {
            $table->boolean('only_tien_cong')->default(false)->after('debtor_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('food_report_debts', function (Blueprint $table) {
            $table->dropColumn('only_tien_cong');
        });
    }
};
