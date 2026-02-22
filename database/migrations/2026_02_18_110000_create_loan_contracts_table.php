<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lender_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('borrower_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('borrower_external_name')->nullable()->comment('Tên bên vay khi không có account');
            $table->string('name')->comment('Tên hợp đồng');
            $table->decimal('principal_at_start', 18, 2)->comment('Số gốc ban đầu - immutable');
            $table->decimal('interest_rate', 10, 4)->default(0);
            $table->string('interest_unit', 20)->default('yearly');
            $table->string('interest_calculation', 20)->default('simple')->comment('simple|compound');
            $table->string('accrual_frequency', 20)->default('daily');
            $table->date('start_date');
            $table->date('due_date')->nullable();
            $table->boolean('auto_accrue')->default(true);
            $table->string('status', 20)->default('pending')->comment('pending|active|closed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_contracts');
    }
};
