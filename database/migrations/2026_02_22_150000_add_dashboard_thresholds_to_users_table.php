<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('balance_change_amount_threshold')->nullable()->after('low_balance_threshold');
            $table->decimal('spend_spike_ratio', 5, 2)->nullable()->after('balance_change_amount_threshold');
            $table->unsignedTinyInteger('week_anomaly_pct')->nullable()->after('spend_spike_ratio');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['balance_change_amount_threshold', 'spend_spike_ratio', 'week_anomaly_pct']);
        });
    }
};
