<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('behavior_events_consent')->default(false)->after('allowed_features')
                ->comment('User consent for behavior micro-events (dwell, scroll, tick timing)');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('behavior_events_consent');
        });
    }
};
