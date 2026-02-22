<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_threshold_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_threshold_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 64);
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at'], 'bt_events_user_created');
            $table->index(['budget_threshold_id', 'event_type', 'created_at'], 'bt_events_threshold_type_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_threshold_events');
    }
};
