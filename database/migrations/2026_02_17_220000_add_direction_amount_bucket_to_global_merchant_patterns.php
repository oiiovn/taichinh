<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('global_merchant_patterns', function (Blueprint $table) {
            $table->string('direction', 10)->default('')->after('merchant_group');
            $table->string('amount_bucket', 20)->default('')->after('direction');
        });

        Schema::table('global_merchant_patterns', function (Blueprint $table) {
            $table->dropUnique('gmp_merchant_group_system_category_unique');
        });

        Schema::table('global_merchant_patterns', function (Blueprint $table) {
            $table->unique(
                ['merchant_group', 'direction', 'amount_bucket', 'system_category_id'],
                'gmp_group_dir_bucket_category_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('global_merchant_patterns', function (Blueprint $table) {
            $table->dropUnique('gmp_group_dir_bucket_category_unique');
        });
        Schema::table('global_merchant_patterns', function (Blueprint $table) {
            $table->unique(['merchant_group', 'system_category_id'], 'gmp_merchant_group_system_category_unique');
        });
        Schema::table('global_merchant_patterns', function (Blueprint $table) {
            $table->dropColumn(['direction', 'amount_bucket']);
        });
    }
};
