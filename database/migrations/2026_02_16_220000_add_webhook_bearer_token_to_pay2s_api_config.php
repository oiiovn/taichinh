<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pay2s_api_config', function (Blueprint $table) {
            $table->string('webhook_bearer_token', 500)->nullable()->after('fetch_end')->comment('Bearer token để xác thực webhook Pay2S');
        });
    }

    public function down(): void
    {
        Schema::table('pay2s_api_config', function (Blueprint $table) {
            $table->dropColumn('webhook_bearer_token');
        });
    }
};
