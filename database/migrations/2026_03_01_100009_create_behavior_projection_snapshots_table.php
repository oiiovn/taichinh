<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('behavior_projection_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('snapshot_at')->useCurrent();
            $table->json('probabilities')->nullable()->comment('probability_maintain_60d, probability_maintain_90d');
            $table->json('risk_weeks')->nullable();
            $table->string('suggestion', 500)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'snapshot_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behavior_projection_snapshots');
    }
};
