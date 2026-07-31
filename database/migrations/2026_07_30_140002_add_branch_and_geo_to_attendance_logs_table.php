<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->foreignId('food_branch_id')
                ->nullable()
                ->after('employee_id')
                ->constrained('food_branches')
                ->nullOnDelete();
            $table->decimal('check_in_latitude', 10, 7)->nullable()->after('note');
            $table->decimal('check_in_longitude', 10, 7)->nullable()->after('check_in_latitude');
            $table->decimal('check_out_latitude', 10, 7)->nullable()->after('check_in_longitude');
            $table->decimal('check_out_longitude', 10, 7)->nullable()->after('check_out_latitude');
            $table->string('check_in_method', 16)->nullable()->after('check_out_longitude');
            $table->string('check_out_method', 16)->nullable()->after('check_in_method');
            $table->unsignedInteger('check_in_distance_meters')->nullable()->after('check_out_method');
            $table->unsignedInteger('check_out_distance_meters')->nullable()->after('check_in_distance_meters');

            $table->index('food_branch_id');
            $table->index(['employee_id', 'food_branch_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropIndex(['employee_id', 'food_branch_id', 'work_date']);
            $table->dropConstrainedForeignId('food_branch_id');
            $table->dropColumn([
                'check_in_latitude',
                'check_in_longitude',
                'check_out_latitude',
                'check_out_longitude',
                'check_in_method',
                'check_out_method',
                'check_in_distance_meters',
                'check_out_distance_meters',
            ]);
        });
    }
};
