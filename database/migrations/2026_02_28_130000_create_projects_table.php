<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('projects')) {
            return;
        }
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 20)->nullable()->default('#6b7280');
            $table->timestamps();
        });
        if (Schema::hasTable('work_tasks') && ! Schema::hasColumn('work_tasks', 'project_id')) {
            Schema::table('work_tasks', function (Blueprint $table) {
                $table->foreignId('project_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('work_tasks') && Schema::hasColumn('work_tasks', 'project_id')) {
            Schema::table('work_tasks', function (Blueprint $table) {
                $table->dropForeign(['project_id']);
            });
        }
        Schema::dropIfExists('projects');
    }
};
