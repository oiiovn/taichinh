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
            if (! Schema::hasColumn('work_tasks', 'remind_minutes_before')) {
                $table->unsignedSmallInteger('remind_minutes_before')->nullable()->after('due_time')->comment('Số phút nhắc trước giờ hạn; null = không nhắc');
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_tasks', function (Blueprint $table) {
            $table->dropColumn('remind_minutes_before');
        });
    }
};
