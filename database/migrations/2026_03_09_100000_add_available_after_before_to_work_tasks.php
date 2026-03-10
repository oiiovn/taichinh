<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('work_tasks')) {
            return;
        }
        Schema::table('work_tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('work_tasks', 'available_after')) {
                $table->time('available_after')->nullable()->after('due_time')->comment('Earliest start time (execution window); null = bất kỳ');
            }
            if (! Schema::hasColumn('work_tasks', 'available_before')) {
                $table->time('available_before')->nullable()->after('available_after')->comment('Latest start time; null = không giới hạn');
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_tasks', function (Blueprint $table) {
            $table->dropColumn(['available_after', 'available_before']);
        });
    }
};
