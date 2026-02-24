<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_state_snapshots') || Schema::hasColumn('financial_state_snapshots', 'execution_consistency_score')) {
            return;
        }
        Schema::table('financial_state_snapshots', function (Blueprint $table) {
            $table->decimal('execution_consistency_score', 5, 2)->nullable()->comment('0-100, từ behavioral profile');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('financial_state_snapshots') || ! Schema::hasColumn('financial_state_snapshots', 'execution_consistency_score')) {
            return;
        }
        Schema::table('financial_state_snapshots', function (Blueprint $table) {
            $table->dropColumn('execution_consistency_score');
        });
    }
};
