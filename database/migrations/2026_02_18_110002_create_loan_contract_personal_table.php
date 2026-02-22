<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_contract_personal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('reminder_at')->nullable();
            $table->string('risk_tag')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['loan_contract_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_contract_personal');
    }
};
