<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_state_snapshots', function (Blueprint $table) {
            $table->string('brain_mode_key', 64)->nullable()->after('narrative_hash')->comment('Brain mode tại thời điểm snapshot (cho monitor)');
            $table->json('decision_bundle_snapshot')->nullable()->after('brain_mode_key')->comment('decision_bundle tại thời điểm snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('financial_state_snapshots', function (Blueprint $table) {
            $table->dropColumn(['brain_mode_key', 'decision_bundle_snapshot']);
        });
    }
};
