<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_ledger_entries', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->after('effective_date');
        });

        Schema::table('loan_ledger_entries', function (Blueprint $table) {
            $table->unique(['loan_contract_id', 'idempotency_key'], 'loan_ledger_entries_contract_idempotency_unique');
        });

        Schema::table('loan_ledger_entries', function (Blueprint $table) {
            $table->index(['loan_contract_id', 'status', 'effective_date'], 'loan_ledger_entries_contract_status_date');
            $table->index(['loan_contract_id', 'type'], 'loan_ledger_entries_contract_type');
        });
    }

    public function down(): void
    {
        Schema::table('loan_ledger_entries', function (Blueprint $table) {
            $table->dropIndex('loan_ledger_entries_contract_status_date');
            $table->dropIndex('loan_ledger_entries_contract_type');
        });
        Schema::table('loan_ledger_entries', function (Blueprint $table) {
            $table->dropUnique('loan_ledger_entries_contract_idempotency_unique');
        });
        Schema::table('loan_ledger_entries', function (Blueprint $table) {
            $table->dropColumn('idempotency_key');
        });
    }
};
