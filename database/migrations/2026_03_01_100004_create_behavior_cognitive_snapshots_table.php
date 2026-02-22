<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('behavior_cognitive_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->decimal('cli', 5, 4)->nullable()->comment('Cognitive Load Index 0-1');
            $table->unsignedInteger('new_tasks_count')->default(0);
            $table->unsignedInteger('active_tasks_count')->default(0);
            $table->unsignedInteger('active_minutes')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behavior_cognitive_snapshots');
    }
};
