<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('work_task_instances')) {
            return;
        }
        Schema::create('work_task_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_task_id')->constrained('work_tasks')->cascadeOnDelete();
            $table->date('instance_date')->comment('Ngày phiên bản công việc');
            $table->string('status', 20)->default('pending')->comment('pending, completed, skipped');
            $table->timestamp('completed_at')->nullable();
            $table->boolean('skipped')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['work_task_id', 'instance_date']);
        });
        Schema::table('work_task_instances', function (Blueprint $table) {
            $table->index('instance_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_task_instances');
    }
};
