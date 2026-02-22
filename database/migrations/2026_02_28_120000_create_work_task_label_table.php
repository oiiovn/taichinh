<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('work_task_label')) {
            return;
        }
        Schema::create('work_task_label', function (Blueprint $table) {
            $table->foreignId('work_task_id')->constrained('work_tasks')->cascadeOnDelete();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->primary(['work_task_id', 'label_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_task_label');
    }
};
