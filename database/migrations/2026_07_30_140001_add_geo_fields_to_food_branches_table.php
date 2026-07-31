<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_branches', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('branch_link');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedInteger('check_in_radius_meters')->default(100)->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('food_branches', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'check_in_radius_meters']);
        });
    }
};
