<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_behavior_profiles', function (Blueprint $table) {
            $table->decimal('execution_consistency_score_reduce_expense', 5, 2)->nullable()->after('execution_consistency_score')->comment('0-100, weighted by decay');
            $table->decimal('execution_consistency_score_debt', 5, 2)->nullable()->after('execution_consistency_score_reduce_expense')->comment('0-100');
            $table->decimal('execution_consistency_score_income', 5, 2)->nullable()->after('execution_consistency_score_debt')->comment('0-100');
        });
    }

    public function down(): void
    {
        Schema::table('user_behavior_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'execution_consistency_score_reduce_expense',
                'execution_consistency_score_debt',
                'execution_consistency_score_income',
            ]);
        });
    }
};
