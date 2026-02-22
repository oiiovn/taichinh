<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_pending_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_contract_id')->constrained()->cascadeOnDelete();
            $table->date('due_date')->comment('Ngày thanh toán');
            $table->decimal('expected_principal', 18, 2)->default(0);
            $table->decimal('expected_interest', 18, 2)->default(0);
            $table->string('match_content', 255)->comment('Nội dung CK render sẵn để match');
            $table->string('status', 30)->default('awaiting_payment')
                ->comment('awaiting_payment|matched_bank|pending_counterparty_confirm|confirmed');
            $table->foreignId('transaction_history_id')->nullable()->constrained('transaction_history')->nullOnDelete();
            $table->string('bank_transaction_ref', 100)->nullable()->comment('Mã GD ngân hàng');
            $table->string('payment_method', 20)->nullable()->comment('bank|cash');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('loan_ledger_entry_id')->nullable()->constrained('loan_ledger_entries')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::table('loan_pending_payments', function (Blueprint $table) {
            $table->index(['loan_contract_id', 'status']);
            $table->index(['due_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_pending_payments');
    }
};
