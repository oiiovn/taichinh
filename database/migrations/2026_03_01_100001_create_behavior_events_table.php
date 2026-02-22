<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('behavior_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 80)->comment('app_open_after_reminder, scroll_count, dwell_ms, task_tick_at, post_tick_action, reminder_read_at, etc.');
            $table->foreignId('work_task_id')->nullable()->constrained('work_tasks')->nullOnDelete();
            $table->json('payload')->nullable()->comment('latency_ms, deadline_at, etc.');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
            $table->index(['work_task_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behavior_events');
    }
};
