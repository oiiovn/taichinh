<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_recipe_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'name']);
        });

        Schema::create('food_recipe_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_recipe_template_id')->constrained('food_recipe_templates')->cascadeOnDelete();
            $table->foreignId('food_material_id')->constrained('food_materials')->cascadeOnDelete();
            $table->decimal('qty_per_unit', 15, 6)->default(0);
            $table->timestamps();

            $table->unique(['food_recipe_template_id', 'food_material_id'], 'food_recipe_tpl_item_unique');
        });

        Schema::table('food_products', function (Blueprint $table) {
            $table->foreignId('food_recipe_template_id')
                ->nullable()
                ->after('is_combo')
                ->constrained('food_recipe_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('food_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('food_recipe_template_id');
        });
        Schema::dropIfExists('food_recipe_template_items');
        Schema::dropIfExists('food_recipe_templates');
    }
};
