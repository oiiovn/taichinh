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
            if (! Schema::hasColumn('work_tasks', 'location')) {
                $table->string('location', 500)->nullable()->after('description_html');
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_tasks', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }
};
