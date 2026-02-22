<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulation_personas', function (Blueprint $table) {
            $table->id();
            $table->string('persona_key', 64)->unique()->comment('persona_1 .. persona_6');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('meta')->nullable()->comment('expected_modes, learning_goals');
            $table->timestamps();
            $table->index('persona_key');
        });

        Schema::create('simulation_drift_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('cycle')->comment('1-24 tháng');
            $table->date('snapshot_date')->comment('Ngày cuối kỳ');
            $table->json('brain_params_snapshot')->nullable()->comment('user_brain_params tại cycle');
            $table->json('drift_signals')->nullable();
            $table->string('brain_mode_key', 64)->nullable();
            $table->decimal('forecast_error', 8, 4)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'cycle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulation_drift_logs');
        Schema::dropIfExists('simulation_personas');
    }
};
