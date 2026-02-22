<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('coaching_intervention_events')) {
            $this->addIndexesIfMissing();
            return;
        }
        Schema::create('coaching_intervention_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('intervention_type', 80)->comment('policy_banner_micro_goal, policy_banner_reduced_reminder, level_up_message, today_message, insight_block');
            $table->json('context')->nullable()->comment('stage, layout, mode, etc.');
            $table->timestamp('shown_at')->useCurrent();
            $table->decimal('outcome_completion_3d', 5, 4)->nullable()->comment('0-1 completion rate in next 3 days');
            $table->smallInteger('outcome_recovery_delta')->nullable()->comment('recovery_days change vs before');
            $table->timestamp('outcome_measured_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'intervention_type', 'shown_at'], 'coaching_int_user_type_shown');
            $table->index(['user_id', 'outcome_measured_at'], 'coaching_int_user_outcome_at');
        });
    }

    private function addIndexesIfMissing(): void
    {
        try {
            Schema::table('coaching_intervention_events', function (Blueprint $table) {
                $table->index(['user_id', 'intervention_type', 'shown_at'], 'coaching_int_user_type_shown');
                $table->index(['user_id', 'outcome_measured_at'], 'coaching_int_user_outcome_at');
            });
        } catch (\Throwable $e) {
            // Indexes may already exist
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coaching_intervention_events');
    }
};
