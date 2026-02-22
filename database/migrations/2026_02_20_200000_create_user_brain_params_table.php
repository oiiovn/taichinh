<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_brain_params', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('param_key', 64)->comment('VD: conservative_bias, dsi_sensitivity');
            $table->decimal('param_value', 12, 6)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'param_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_brain_params');
    }
};
