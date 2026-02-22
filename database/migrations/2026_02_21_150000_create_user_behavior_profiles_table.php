<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_behavior_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('debt_style', 32)->nullable()->comment('snowball, avalanche, mixed, unknown');
            $table->string('risk_tolerance', 32)->nullable()->comment('low, medium, high');
            $table->decimal('spending_discipline_score', 5, 2)->nullable()->comment('0-100');
            $table->decimal('execution_consistency_score', 5, 2)->nullable()->comment('0-100');
            $table->string('surplus_usage_pattern', 64)->nullable()->comment('low_reserve_builder, saves_first, ...');
            $table->string('volatility_reaction_pattern', 64)->nullable()->comment('income_elastic_spender, ...');
            $table->string('risk_underestimation_flag', 8)->nullable()->comment('yes, no');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_behavior_profiles');
    }
};
