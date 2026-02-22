<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('budget_thresholds')) {
            Schema::create('budget_thresholds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('name', 255);
                $table->unsignedBigInteger('amount_limit_vnd');
                $table->string('period_type', 32);
                $table->unsignedSmallInteger('year')->nullable();
                $table->unsignedTinyInteger('month')->nullable();
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->json('category_bindings');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('budget_threshold_snapshots')) {
            Schema::create('budget_threshold_snapshots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('budget_threshold_id')->constrained()->cascadeOnDelete();
                $table->string('period_key', 32);
                $table->date('period_start');
                $table->date('period_end');
                $table->unsignedBigInteger('amount_limit_vnd');
                $table->unsignedBigInteger('total_spent_vnd')->default(0);
                $table->decimal('deviation_pct', 10, 2)->nullable();
                $table->boolean('breached')->default(false);
                $table->timestamps();
                $table->index(['budget_threshold_id', 'period_key']);
            });
        }
    }

    public function down(): void
    {
        // Không drop để tránh mất dữ liệu khi rollback
    }
};
