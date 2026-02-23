<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_state_snapshots') || Schema::hasColumn('financial_state_snapshots', 'projected_income_monthly')) {
            return;
        }
        Schema::table('financial_state_snapshots', function (Blueprint $table) {
            $table->decimal('projected_income_monthly', 18, 2)->nullable()->comment('Thu dự báo tháng kế (để so sánh actual)');
            $table->decimal('projected_expense_monthly', 18, 2)->nullable()->after('projected_income_monthly');
            $table->decimal('forecast_error', 8, 4)->nullable()->after('projected_expense_monthly')->comment('|actual - projected| / projected, cập nhật sau khi có actual');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('financial_state_snapshots') || ! Schema::hasColumn('financial_state_snapshots', 'projected_income_monthly')) {
            return;
        }
        Schema::table('financial_state_snapshots', function (Blueprint $table) {
            $table->dropColumn(['projected_income_monthly', 'projected_expense_monthly', 'forecast_error']);
        });
    }
};
