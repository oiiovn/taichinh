<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('behavior_temporal_aggregates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('variance_score', 12, 6)->nullable();
            $table->decimal('drift_slope', 12, 6)->nullable()->comment('Discipline drift slope');
            $table->string('streak_risk', 20)->nullable()->comment('e.g. low, medium, high');
            $table->timestamps();
            $table->index(['user_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behavior_temporal_aggregates');
    }
};
