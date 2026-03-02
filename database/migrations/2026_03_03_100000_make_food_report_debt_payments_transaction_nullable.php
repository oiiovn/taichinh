<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_report_debt_payments', function (Blueprint $table) {
            $table->dropForeign(['transaction_history_id']);
            $table->dropUnique(['transaction_history_id']);
        });
        Schema::table('food_report_debt_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('transaction_history_id')->nullable()->change();
            $table->foreign('transaction_history_id')->references('id')->on('transaction_history')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('food_report_debt_payments', function (Blueprint $table) {
            $table->dropForeign(['transaction_history_id']);
        });
        Schema::table('food_report_debt_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('transaction_history_id')->nullable(false)->change();
            $table->foreign('transaction_history_id')->references('id')->on('transaction_history')->cascadeOnDelete();
            $table->unique('transaction_history_id');
        });
    }
};
