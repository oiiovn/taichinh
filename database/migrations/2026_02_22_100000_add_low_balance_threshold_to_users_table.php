<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('low_balance_threshold')->nullable()->after('allowed_features')->comment('Ngưỡng cảnh báo số dư thấp (VND), null = dùng mặc định 500000');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('low_balance_threshold');
        });
    }
};
