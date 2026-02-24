<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('expected_amount', 18, 2)->default(0);
            $table->string('frequency', 30)->default('monthly'); // monthly | quarterly | custom_days
            $table->unsignedSmallInteger('interval_value')->default(1);
            $table->date('next_due_date');
            $table->json('keywords')->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('transfer_note_pattern', 255)->nullable();
            $table->string('status', 20)->default('active'); // active | paused | ended
            $table->unsignedTinyInteger('reliability_score')->nullable(); // 0-100
            $table->foreignId('last_matched_transaction_id')->nullable()->constrained('transaction_history')->nullOnDelete();
            $table->date('last_paid_date')->nullable();
            $table->unsignedSmallInteger('grace_window_days')->default(7);
            $table->timestamps();
        });

        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->index(['user_id', 'status']);
            $table->index('next_due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_schedules');
    }
};
