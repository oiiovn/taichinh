<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pay2s_bank_account_id')->nullable()->constrained('pay2s_bank_accounts')->nullOnDelete();
            $table->string('external_id')->unique()->comment('Mã giao dịch từ API');
            $table->string('account_number', 50)->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('type', 10)->comment('IN | OUT');
            $table->text('description')->nullable();
            $table->dateTime('transaction_date')->nullable();
            $table->json('raw_json')->nullable();
            $table->timestamps();
        });

        Schema::table('transaction_history', function (Blueprint $table) {
            $table->index(['transaction_date', 'type']);
            $table->index('account_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_history');
    }
};
