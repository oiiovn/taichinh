<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('work_tasks') || Schema::hasColumn('work_tasks', 'internalized_at')) {
            return;
        }
        Schema::table('work_tasks', function (Blueprint $table) {
            $table->timestamp('internalized_at')->nullable()
                ->comment('Habit internalized: low variance, before deadline, few reminders');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('work_tasks') || ! Schema::hasColumn('work_tasks', 'internalized_at')) {
            return;
        }
        Schema::table('work_tasks', function (Blueprint $table) {
            $table->dropColumn('internalized_at');
        });
    }
};
