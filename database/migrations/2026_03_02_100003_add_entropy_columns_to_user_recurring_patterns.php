<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_recurring_patterns', function (Blueprint $table) {
            $table->decimal('interval_variance', 12, 4)->nullable()->after('interval_std');
            $table->decimal('amount_cv', 8, 4)->nullable()->after('interval_variance')->comment('Coefficient of variation amount');
            $table->unsignedSmallInteger('miss_streak')->default(0)->after('amount_cv')->comment('Số kỳ liên tiếp không match');
        });
    }

    public function down(): void
    {
        Schema::table('user_recurring_patterns', function (Blueprint $table) {
            $table->dropColumn(['interval_variance', 'amount_cv', 'miss_streak']);
        });
    }
};
