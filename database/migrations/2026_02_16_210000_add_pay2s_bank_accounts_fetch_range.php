<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pay2s_api_config', function (Blueprint $table) {
            $table->text('bank_accounts')->nullable()->after('path_transactions')->comment('Số TK ngân hàng cách nhau bởi dấu phẩy (cho API my.pay2s.vn)');
            $table->string('fetch_begin', 20)->nullable()->after('bank_accounts')->comment('dd/mm/yyyy');
            $table->string('fetch_end', 20)->nullable()->after('fetch_begin')->comment('dd/mm/yyyy');
        });
    }

    public function down(): void
    {
        Schema::table('pay2s_api_config', function (Blueprint $table) {
            $table->dropColumn(['bank_accounts', 'fetch_begin', 'fetch_end']);
        });
    }
};
