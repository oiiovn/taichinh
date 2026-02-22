<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_thresholds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->unsignedBigInteger('amount_limit_vnd');
            $table->string('period_type', 32); // month, custom
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedTinyInteger('month')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->json('category_bindings'); // [{"type":"user_category","id":12},{"type":"system_category","id":5}]
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('budget_threshold_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_threshold_id')->constrained()->cascadeOnDelete();
            $table->string('period_key', 32); // Y-m or Y-m-d range
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('amount_limit_vnd');
            $table->unsignedBigInteger('total_spent_vnd')->default(0);
            $table->decimal('deviation_pct', 10, 2)->nullable(); // (spent - limit)/limit * 100
            $table->boolean('breached')->default(false);
            $table->timestamps();
        });

        Schema::table('budget_threshold_snapshots', function (Blueprint $table) {
            $table->index(['budget_threshold_id', 'period_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_threshold_snapshots');
        Schema::dropIfExists('budget_thresholds');
    }
};
