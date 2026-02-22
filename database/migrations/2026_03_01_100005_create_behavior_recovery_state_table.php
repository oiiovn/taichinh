<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('behavior_recovery_state', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('last_fail_at')->nullable();
            $table->unsignedSmallInteger('recovery_days')->nullable()->comment('Days from fail to stable');
            $table->unsignedSmallInteger('streak_after_recovery')->default(0);
            $table->timestamps();
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behavior_recovery_state');
    }
};
