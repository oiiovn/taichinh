<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_buff_labor_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('paid_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('payment_method', 20)->default('cash');
            $table->string('note', 500)->nullable();
            $table->dateTime('paid_at');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['payer_user_id', 'paid_at']);
            $table->index(['paid_user_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_buff_labor_payments');
    }
};
