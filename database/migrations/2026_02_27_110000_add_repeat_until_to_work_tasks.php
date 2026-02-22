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
            if (! Schema::hasColumn('work_tasks', 'repeat_until')) {
                $table->date('repeat_until')->nullable()->after('repeat');
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_tasks', function (Blueprint $table) {
            $table->dropColumn('repeat_until');
        });
    }
};
