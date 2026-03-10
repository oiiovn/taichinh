<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('work_task_instances')) {
            return;
        }
        Schema::table('work_task_instances', function (Blueprint $table) {
            if (! Schema::hasColumn('work_task_instances', 'focus_started_at')) {
                $table->timestamp('focus_started_at')->nullable()->after('actual_duration');
            }
            if (! Schema::hasColumn('work_task_instances', 'focus_last_activity_at')) {
                $table->timestamp('focus_last_activity_at')->nullable()->after('focus_started_at');
            }
            if (! Schema::hasColumn('work_task_instances', 'focus_stopped_at')) {
                $table->timestamp('focus_stopped_at')->nullable()->after('focus_last_activity_at');
            }
            if (! Schema::hasColumn('work_task_instances', 'focus_recorded_minutes')) {
                $table->unsignedInteger('focus_recorded_minutes')->nullable()->after('focus_stopped_at')
                    ->comment('Phút tin cậy (idle/stop/sanity), không dùng complete_time - start');
            }
            if (! Schema::hasColumn('work_task_instances', 'ghost_completion_detected')) {
                $table->boolean('ghost_completion_detected')->default(false)->after('focus_recorded_minutes');
            }
            if (! Schema::hasColumn('work_task_instances', 'ghost_completion_confirmed')) {
                $table->boolean('ghost_completion_confirmed')->nullable()->after('ghost_completion_detected');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('work_task_instances')) {
            return;
        }
        Schema::table('work_task_instances', function (Blueprint $table) {
            foreach ([
                'ghost_completion_confirmed',
                'ghost_completion_detected',
                'focus_recorded_minutes',
                'focus_stopped_at',
                'focus_last_activity_at',
                'focus_started_at',
            ] as $col) {
                if (Schema::hasColumn('work_task_instances', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
