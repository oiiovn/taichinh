<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_sales_reports', function (Blueprint $table) {
            $table->foreignId('food_branch_id')->nullable()->after('user_id')->constrained('food_branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('food_sales_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('food_branch_id');
        });
    }
};
