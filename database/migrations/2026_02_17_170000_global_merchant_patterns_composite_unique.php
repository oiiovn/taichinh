<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('global_merchant_patterns', function (Blueprint $table) {
            $table->dropUnique(['merchant_key']);
        });

        Schema::table('global_merchant_patterns', function (Blueprint $table) {
            $table->unique(['merchant_key', 'system_category_id']);
            $table->index('merchant_key');
        });
    }

    public function down(): void
    {
        Schema::table('global_merchant_patterns', function (Blueprint $table) {
            $table->dropUnique(['merchant_key', 'system_category_id']);
            $table->dropIndex(['merchant_key']);
        });

        Schema::table('global_merchant_patterns', function (Blueprint $table) {
            $table->unique('merchant_key');
        });
    }
};
