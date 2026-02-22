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
            if (! Schema::hasColumn('work_tasks', 'program_id')) {
                $table->foreignId('program_id')->nullable()->after('project_id')->constrained('behavior_programs')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('work_tasks', 'program_id')) {
                $table->dropForeign(['program_id']);
            }
        });
    }
};
