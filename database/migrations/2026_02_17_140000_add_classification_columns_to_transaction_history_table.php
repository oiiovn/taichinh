<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_history', function (Blueprint $table) {
            $table->string('merchant_key', 255)->nullable()->after('description');
            $table->foreignId('system_category_id')->nullable()->after('merchant_key')->constrained('system_categories')->nullOnDelete();
            $table->foreignId('user_category_id')->nullable()->after('system_category_id')->constrained('user_categories')->nullOnDelete();
            $table->string('classification_status', 50)->default('pending')->after('user_category_id');
            $table->float('classification_confidence')->nullable()->after('classification_status');
            $table->string('classification_version', 50)->nullable()->after('classification_confidence');
        });

        Schema::table('transaction_history', function (Blueprint $table) {
            $table->index('merchant_key');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_history', function (Blueprint $table) {
            $table->dropForeign(['system_category_id']);
            $table->dropForeign(['user_category_id']);
            $table->dropIndex(['merchant_key']);
        });

        Schema::table('transaction_history', function (Blueprint $table) {
            $table->dropColumn([
                'merchant_key',
                'system_category_id',
                'user_category_id',
                'classification_status',
                'classification_confidence',
                'classification_version',
            ]);
        });
    }
};
