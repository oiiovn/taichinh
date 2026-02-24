<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->boolean('amount_is_variable')->default(false)->after('expected_amount');
            $table->string('currency', 10)->nullable()->default('VND')->after('amount_is_variable');
            $table->text('internal_note')->nullable()->after('currency');
            $table->unsignedSmallInteger('reminder_days')->nullable()->after('grace_window_days');
            $table->unsignedTinyInteger('day_of_month')->nullable()->after('next_due_date'); // 1-31
            $table->decimal('amount_tolerance_pct', 5, 2)->nullable()->after('transfer_note_pattern'); // e.g. 5.00 = ±5%
            $table->boolean('auto_update_amount')->default(false)->after('amount_tolerance_pct');
            $table->boolean('reliability_tracking')->default(true)->after('status');
            $table->boolean('overdue_alert')->default(true)->after('reliability_tracking');
            $table->boolean('auto_advance_on_match')->default(true)->after('overdue_alert');
        });
    }

    public function down(): void
    {
        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->dropColumn([
                'amount_is_variable', 'currency', 'internal_note', 'reminder_days',
                'day_of_month', 'amount_tolerance_pct', 'auto_update_amount',
                'reliability_tracking', 'overdue_alert', 'auto_advance_on_match',
            ]);
        });
    }
};
