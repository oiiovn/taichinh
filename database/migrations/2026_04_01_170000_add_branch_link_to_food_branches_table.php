<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_branches', function (Blueprint $table) {
            $table->string('branch_link', 500)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('food_branches', function (Blueprint $table) {
            $table->dropColumn('branch_link');
        });
    }
};

