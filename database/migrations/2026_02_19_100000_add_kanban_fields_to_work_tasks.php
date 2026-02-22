<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_tasks', function (Blueprint $table) {
            $table->string('kanban_status', 20)->default('backlog')->after('completed')
                ->comment('backlog, this_cycle, in_progress, done');
            $table->string('category', 20)->nullable()->after('kanban_status')
                ->comment('revenue, growth, maintenance');
            $table->unsignedInteger('estimated_duration')->nullable()->after('category')->comment('Phút');
            $table->unsignedInteger('actual_duration')->nullable()->after('estimated_duration')->comment('Phút');
            $table->string('impact', 20)->nullable()->after('actual_duration')->comment('Mini impact: high, medium, low');
        });
        \DB::table('work_tasks')->where('completed', true)->update(['kanban_status' => 'done']);
    }

    public function down(): void
    {
        Schema::table('work_tasks', function (Blueprint $table) {
            $table->dropColumn(['kanban_status', 'category', 'estimated_duration', 'actual_duration', 'impact']);
        });
    }
};
