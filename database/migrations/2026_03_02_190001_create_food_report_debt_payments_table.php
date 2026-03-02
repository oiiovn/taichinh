<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_report_debt_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_report_debt_id')->constrained('food_report_debts')->cascadeOnDelete();
            $table->foreignId('transaction_history_id')->constrained('transaction_history')->cascadeOnDelete();
            $table->decimal('amount_paid', 18, 0)->unsigned();
            $table->timestamps();
            $table->unique('food_report_debt_id');
            $table->unique('transaction_history_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_report_debt_payments');
    }
};
