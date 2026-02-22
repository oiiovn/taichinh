<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('behavior_trust_gradients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('trust_execution', 5, 4)->default(0.5)->comment('0-1');
            $table->decimal('trust_honesty', 5, 4)->default(0.5)->comment('0-1');
            $table->decimal('trust_consistency', 5, 4)->default(0.5)->comment('0-1');
            $table->timestamps();
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behavior_trust_gradients');
    }
};
