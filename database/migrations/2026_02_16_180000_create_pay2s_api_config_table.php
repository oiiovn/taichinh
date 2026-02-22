<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay2s_api_config', function (Blueprint $table) {
            $table->id();
            $table->string('api_key')->nullable()->comment('VD: PAY2S23DW78K2CVCZFW9');
            $table->string('token_1')->nullable()->comment('Token/Secret 1');
            $table->string('token_2')->nullable()->comment('Token/Secret 2');
            $table->string('base_url', 500)->nullable()->comment('Base URL API Pay2s');
            $table->string('path_accounts', 255)->nullable()->comment('VD: api/accounts hoặc historyapivcbv2/{token}');
            $table->string('path_transactions', 255)->nullable()->comment('VD: api/transactions hoặc historyapivcbv2/{token}');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay2s_api_config');
    }
};
