<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pay2s_api_config', function (Blueprint $table) {
            $table->string('partner_code', 100)->nullable()->after('id')->comment('Partner code Pay2s');
            $table->string('access_key', 255)->nullable()->after('partner_code')->comment('Access key');
            $table->string('secret_key', 255)->nullable()->after('access_key')->comment('Secret key');
        });

        if (Schema::hasColumn('pay2s_api_config', 'api_key')) {
            \Illuminate\Support\Facades\DB::statement('UPDATE pay2s_api_config SET partner_code = api_key, access_key = token_1, secret_key = token_2');
            Schema::table('pay2s_api_config', function (Blueprint $table) {
                $table->dropColumn(['api_key', 'token_1', 'token_2']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('pay2s_api_config', function (Blueprint $table) {
            $table->string('api_key')->nullable()->after('id');
            $table->string('token_1')->nullable()->after('api_key');
            $table->string('token_2')->nullable()->after('token_1');
        });
        \Illuminate\Support\Facades\DB::statement('UPDATE pay2s_api_config SET api_key = partner_code, token_1 = access_key, token_2 = secret_key');
        Schema::table('pay2s_api_config', function (Blueprint $table) {
            $table->dropColumn(['partner_code', 'access_key', 'secret_key']);
        });
    }
};
