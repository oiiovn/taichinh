<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('income_goals', function (Blueprint $table) {
            $table->json('expense_category_bindings')->nullable()->after('category_bindings');
        });
    }

    public function down(): void
    {
        Schema::table('income_goals', function (Blueprint $table) {
            $table->dropColumn('expense_category_bindings');
        });
    }
};
