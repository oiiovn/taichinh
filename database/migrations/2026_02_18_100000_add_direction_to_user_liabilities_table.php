<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_liabilities', function (Blueprint $table) {
            $table->string('direction', 20)->default('payable')->after('user_id')->comment('payable = nợ (đi vay), receivable = khoản cho vay');
        });
    }

    public function down(): void
    {
        Schema::table('user_liabilities', function (Blueprint $table) {
            $table->dropColumn('direction');
        });
    }
};
