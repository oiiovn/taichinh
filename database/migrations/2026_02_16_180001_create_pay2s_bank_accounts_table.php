<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay2s_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique()->comment('ID từ Pay2s');
            $table->string('account_number')->nullable();
            $table->string('account_holder_name')->nullable();
            $table->string('bank_code', 50)->nullable();
            $table->string('bank_name')->nullable();
            $table->decimal('balance', 18, 2)->default(0);
            $table->json('raw_json')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay2s_bank_accounts');
    }
};
