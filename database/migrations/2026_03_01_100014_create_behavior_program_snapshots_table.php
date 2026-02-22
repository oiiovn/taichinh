<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('behavior_program_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained('behavior_programs')->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->decimal('integrity_score', 5, 4)->nullable()->comment('0-1: completion consistency, trust, recovery, variance');
            $table->decimal('completion_rate', 5, 4)->nullable()->comment('Tỷ lệ ngày đạt target trong program');
            $table->string('outcome', 30)->nullable()->comment('complete, complete_with_drift, partial, early_internalization, failed');
            $table->json('risk_dates')->nullable()->comment('Ngày có nguy cơ fail');
            $table->timestamps();
            $table->unique(['program_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behavior_program_snapshots');
    }
};
