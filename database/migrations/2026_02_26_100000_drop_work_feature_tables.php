<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('work_weekly_snapshots')) {
            Schema::dropIfExists('work_weekly_snapshots');
        }
        if (Schema::hasTable('work_discipline_snapshots')) {
            Schema::dropIfExists('work_discipline_snapshots');
        }
        if (Schema::hasTable('work_seasons')) {
            Schema::dropIfExists('work_seasons');
        }
        if (Schema::hasTable('work_time_logs')) {
            Schema::dropIfExists('work_time_logs');
        }
        if (Schema::hasTable('work_tasks')) {
            Schema::dropIfExists('work_tasks');
        }
        if (Schema::hasTable('work_projects')) {
            Schema::dropIfExists('work_projects');
        }
        if (Schema::hasTable('work_goals')) {
            Schema::dropIfExists('work_goals');
        }
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'work_cycle_start_date')) {
                    $table->dropColumn('work_cycle_start_date');
                }
                if (Schema::hasColumn('users', 'work_cycle_type')) {
                    $table->dropColumn('work_cycle_type');
                }
            });
        }
    }

    public function down(): void
    {
        // Không khôi phục lại bảng/tính năng Công việc.
    }
};
