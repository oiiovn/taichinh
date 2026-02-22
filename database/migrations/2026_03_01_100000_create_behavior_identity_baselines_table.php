<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('behavior_identity_baselines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('chronotype', 50)->nullable()->comment('early, intermediate, late, etc.');
            $table->decimal('sleep_stability_score', 5, 4)->nullable()->comment('0-1');
            $table->decimal('energy_amplitude', 5, 4)->nullable()->comment('0-1 biên độ dao động năng lượng');
            $table->string('procrastination_pattern', 100)->nullable()->comment('e.g. deadline_rush, avoid, etc.');
            $table->string('stress_response', 100)->nullable()->comment('e.g. freeze, focus, etc.');
            $table->json('bsv_vector')->nullable()->comment('Behavior Signature Vector');
            $table->timestamps();
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behavior_identity_baselines');
    }
};
