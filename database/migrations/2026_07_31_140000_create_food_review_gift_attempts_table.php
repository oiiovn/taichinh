<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_review_gift_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('order_code_input', 80);
            $table->string('order_code_normalized', 80)->nullable()->index();
            $table->foreignId('food_review_id')->nullable()->constrained('food_reviews')->nullOnDelete();
            $table->string('result', 40)->index();
            $table->string('result_message', 255)->nullable();
            $table->string('gift_code', 20)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index(['created_at']);
            $table->index(['result', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_review_gift_attempts');
    }
};
