<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_insight_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('insight_hash', 64)->index();
            $table->string('root_cause', 64)->nullable();
            $table->string('suggested_action_type', 64)->nullable();
            $table->string('feedback_type', 32); // agree, infeasible, incorrect, alternative
            $table->string('reason_code', 64)->nullable(); // cannot_increase_income, cannot_reduce_expense, no_more_borrowing, no_asset_sale
            $table->timestamps();
        });

        Schema::table('financial_insight_feedback', function (Blueprint $table) {
            $table->index(['user_id', 'feedback_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_insight_feedback');
    }
};
