<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_recipe_templates', function (Blueprint $table) {
            $table->decimal('batch_yield', 15, 6)->default(1)->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('food_recipe_templates', function (Blueprint $table) {
            $table->dropColumn('batch_yield');
        });
    }
};
