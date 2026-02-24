<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_income_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('source_type', 50)->default('other'); // business | salary | freelance | rental | other
            $table->string('detection_mode', 20)->default('hybrid'); // keyword | merchant | category | hybrid
            $table->decimal('stability_score', 5, 4)->nullable(); // 0-1
            $table->decimal('volatility_score', 5, 4)->nullable(); // 0-1
            $table->boolean('is_active')->default(true);
            $table->string('created_from', 30)->default('manual'); // goal | manual | learned
            $table->foreignId('income_goal_id')->nullable()->constrained('income_goals')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('user_income_sources', function (Blueprint $table) {
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_income_sources');
    }
};
