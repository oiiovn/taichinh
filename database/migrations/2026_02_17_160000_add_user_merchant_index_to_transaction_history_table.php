<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_history', function (Blueprint $table) {
            $table->index(['user_id', 'merchant_key']);
        });
    }

    public function down(): void
    {
        Schema::table('transaction_history', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'merchant_key']);
        });
    }
};
