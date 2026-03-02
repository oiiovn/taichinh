<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ma_hang', 100);
            $table->string('ten_hang')->nullable();
            $table->decimal('gia_von', 15, 2)->unsigned()->default(0);
            $table->boolean('is_combo')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'ma_hang']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_products');
    }
};
