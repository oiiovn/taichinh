<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pay2s_api_config')) {
            if (! Schema::hasColumn('pay2s_api_config', 'partner_code')) {
                Schema::table('pay2s_api_config', function (Blueprint $table) {
                    $table->string('partner_code', 100)->nullable()->after('id');
                    $table->string('access_key', 255)->nullable()->after('partner_code');
                    $table->string('secret_key', 255)->nullable()->after('access_key');
                });
                if (Schema::hasColumn('pay2s_api_config', 'api_key')) {
                    \Illuminate\Support\Facades\DB::statement('UPDATE pay2s_api_config SET partner_code = api_key, access_key = token_1, secret_key = token_2');
                    Schema::table('pay2s_api_config', function (Blueprint $table) {
                        $table->dropColumn(['api_key', 'token_1', 'token_2']);
                    });
                }
            }
            return;
        }

        Schema::create('pay2s_api_config', function (Blueprint $table) {
            $table->id();
            $table->string('partner_code', 100)->nullable()->comment('Partner Code Pay2S');
            $table->string('access_key', 255)->nullable()->comment('Access Key');
            $table->string('secret_key', 255)->nullable()->comment('Secret Key');
            $table->string('base_url', 500)->nullable()->comment('Base URL, VD: https://sandbox-payment.pay2s.vn');
            $table->string('path_accounts', 255)->nullable();
            $table->string('path_transactions', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay2s_api_config');
    }
};
