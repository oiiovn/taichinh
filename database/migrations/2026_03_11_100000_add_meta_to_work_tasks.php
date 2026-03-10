<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('work_tasks') || Schema::hasColumn('work_tasks', 'meta')) {
            return;
        }
        Schema::table('work_tasks', function (Blueprint $table) {
            $table->json('meta')->nullable()->after('repeat_interval')->comment('energy_affinity, energy_confidence, ...');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('work_tasks', 'meta')) {
            Schema::table('work_tasks', function (Blueprint $table) {
                $table->dropColumn('meta');
            });
        }
    }
};
