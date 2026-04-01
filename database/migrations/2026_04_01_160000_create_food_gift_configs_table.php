<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_gift_configs', function (Blueprint $table) {
            $table->id();
            $table->string('item_name', 255)->default('Bánh Tráng Trộn');
            $table->string('item_image_path', 255)->nullable();
            $table->unsignedInteger('item_value')->default(34000);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_gift_configs');
    }
};

