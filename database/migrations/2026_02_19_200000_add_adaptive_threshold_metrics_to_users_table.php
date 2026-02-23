<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'volatility_score_income')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('volatility_score_income', 5, 4)->nullable()->comment('0..1, cao = biến động mạnh');
            $table->decimal('volatility_score_expense', 5, 4)->nullable()->after('volatility_score_income');
            $table->unsignedBigInteger('avg_transaction_size')->nullable()->after('volatility_score_expense')->comment('VND');
            $table->unsignedBigInteger('median_daily_spend')->nullable()->after('avg_transaction_size')->comment('VND');
            $table->decimal('income_stability_index', 5, 4)->nullable()->after('median_daily_spend')->comment('0..1, cao = ổn định');
            $table->timestamp('threshold_metrics_computed_at')->nullable()->after('income_stability_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'volatility_score_income')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'volatility_score_income',
                'volatility_score_expense',
                'avg_transaction_size',
                'median_daily_spend',
                'income_stability_index',
                'threshold_metrics_computed_at',
            ]);
        });
    }
};
