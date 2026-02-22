<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('work_tasks')) {
            return;
        }
        Schema::create('work_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('description_html')->nullable();
            $table->unsignedTinyInteger('priority')->nullable()->comment('1=Khẩn cấp, 2=Cao, 3=Trung bình, 4=Thấp');
            $table->date('due_date')->nullable();
            $table->time('due_time')->nullable();
            $table->string('repeat', 20)->nullable()->default('none');
            $table->boolean('completed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_tasks');
    }
};
