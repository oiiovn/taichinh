<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liability_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liability_id')->constrained('user_liabilities')->cascadeOnDelete();
            $table->decimal('amount', 18, 2)->default(0);
            $table->date('paid_at');
            $table->decimal('principal_portion', 18, 2)->default(0);
            $table->decimal('interest_portion', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liability_payments');
    }
};
