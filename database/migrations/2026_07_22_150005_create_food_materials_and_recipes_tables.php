<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64)->nullable();
            $table->string('name');
            $table->string('type', 32)->default('nguyen_lieu'); // nguyen_lieu | bao_bi
            $table->string('unit', 32)->default('cái');
            $table->decimal('stock_on_hand', 15, 4)->default(0);
            $table->decimal('reorder_point', 15, 4)->default(0);
            $table->unsignedBigInteger('last_unit_cost')->nullable(); // VND
            $table->string('note', 500)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'code']);
            $table->index(['user_id', 'type', 'active']);
        });

        Schema::create('food_product_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_product_id')->constrained('food_products')->cascadeOnDelete();
            $table->foreignId('food_material_id')->constrained('food_materials')->cascadeOnDelete();
            $table->decimal('qty_per_unit', 15, 6)->default(0); // định lượng / 1 sp bán
            $table->timestamps();

            $table->unique(['food_product_id', 'food_material_id']);
        });

        Schema::create('food_material_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_material_id')->constrained('food_materials')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16); // in | out | adjust
            $table->decimal('qty', 15, 4); // luôn dương; chiều theo type
            $table->decimal('stock_after', 15, 4)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['food_material_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_material_stock_movements');
        Schema::dropIfExists('food_product_recipes');
        Schema::dropIfExists('food_materials');
    }
};
