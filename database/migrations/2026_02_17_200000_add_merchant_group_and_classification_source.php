<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_history', function (Blueprint $table) {
            $table->string('merchant_group', 255)->nullable()->after('merchant_key');
            $table->string('classification_source', 50)->nullable()->after('classification_status');
        });

        Schema::table('global_merchant_patterns', function (Blueprint $table) {
            $table->string('merchant_group', 255)->nullable()->after('merchant_key');
        });

        DB::table('global_merchant_patterns')->whereNotNull('merchant_key')->update([
            'merchant_group' => DB::raw('merchant_key'),
        ]);

        Schema::table('global_merchant_patterns', function (Blueprint $table) {
            $table->unique(['merchant_group', 'system_category_id'], 'gmp_merchant_group_system_category_unique');
        });
    }

    public function down(): void
    {
        Schema::table('global_merchant_patterns', function (Blueprint $table) {
            $table->dropUnique('gmp_merchant_group_system_category_unique');
        });
        Schema::table('global_merchant_patterns', function (Blueprint $table) {
            $table->dropColumn('merchant_group');
        });
        Schema::table('transaction_history', function (Blueprint $table) {
            $table->dropColumn(['merchant_group', 'classification_source']);
        });
    }
};
