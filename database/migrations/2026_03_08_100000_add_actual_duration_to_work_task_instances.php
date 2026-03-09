<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('work_task_instances') && ! Schema::hasColumn('work_task_instances', 'actual_duration')) {
            Schema::table('work_task_instances', function (Blueprint $table) {
                $table->unsignedInteger('actual_duration')->nullable()->after('completed_at')->comment('Phút thực tế hoàn thành (duration learning)');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('work_task_instances', 'actual_duration')) {
            Schema::table('work_task_instances', function (Blueprint $table) {
                $table->dropColumn('actual_duration');
            });
        }
    }
};
