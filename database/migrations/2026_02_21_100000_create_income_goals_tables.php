<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('income_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->unsignedBigInteger('amount_target_vnd');
            $table->string('period_type', 32);
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedTinyInteger('month')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->json('category_bindings');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('income_goal_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('income_goal_id')->constrained()->cascadeOnDelete();
            $table->string('period_key', 32);
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('amount_target_vnd');
            $table->unsignedBigInteger('total_earned_vnd')->default(0);
            $table->decimal('achievement_pct', 10, 2)->nullable();
            $table->boolean('met')->default(false);
            $table->timestamps();
            $table->index(['income_goal_id', 'period_key']);
        });

        Schema::create('income_goal_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('income_goal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 64);
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at'], 'ig_events_user_created');
            $table->index(['income_goal_id', 'event_type', 'created_at'], 'ig_events_goal_type_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('income_goal_events');
        Schema::dropIfExists('income_goal_snapshots');
        Schema::dropIfExists('income_goals');
    }
};
