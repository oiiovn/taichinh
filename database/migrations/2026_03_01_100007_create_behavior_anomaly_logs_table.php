<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('behavior_anomaly_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('detected_at')->useCurrent();
            $table->string('period_type', 50)->nullable()->comment('e.g. week_of_month, day_of_week');
            $table->string('message_key', 100)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behavior_anomaly_logs');
    }
};
