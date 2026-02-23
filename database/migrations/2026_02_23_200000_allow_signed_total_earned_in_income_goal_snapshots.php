<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE income_goal_snapshots MODIFY total_earned_vnd BIGINT NOT NULL DEFAULT 0');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE income_goal_snapshots MODIFY total_earned_vnd BIGINT UNSIGNED NOT NULL DEFAULT 0');
        }
    }
};
