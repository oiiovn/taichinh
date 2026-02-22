<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_contract_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->comment('accrual|payment|adjustment');
            $table->decimal('principal_delta', 18, 2)->default(0);
            $table->decimal('interest_delta', 18, 2)->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('system')->comment('system|lender|borrower');
            $table->string('status', 20)->default('confirmed')->comment('pending|confirmed|rejected');
            $table->json('meta')->nullable();
            $table->date('effective_date')->nullable()->comment('Ngày hiệu lực (cho accrual/payment)');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_ledger_entries');
    }
};
