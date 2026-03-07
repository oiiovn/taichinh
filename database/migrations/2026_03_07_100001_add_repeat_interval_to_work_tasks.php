<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('work_tasks') || Schema::hasColumn('work_tasks', 'repeat_interval')) {
            return;
        }
        Schema::table('work_tasks', function (Blueprint $table) {
            $table->unsignedSmallInteger('repeat_interval')->default(1)->after('repeat')->comment('Mỗi N ngày/tuần/tháng');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('work_tasks') || ! Schema::hasColumn('work_tasks', 'repeat_interval')) {
            return;
        }
        Schema::table('work_tasks', function (Blueprint $table) {
            $table->dropColumn('repeat_interval');
        });
    }
};
