<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('customer_key', 255)->comment('Chuẩn hóa (lowercase) để gom trùng');
            $table->string('customer_name')->comment('Tên hiển thị');
            $table->date('first_order_date')->nullable();
            $table->date('last_order_date')->nullable();
            $table->unsignedInteger('order_count')->default(0);
            $table->boolean('is_returning_customer')->default(false)->comment('order_count >= 2');
            $table->timestamps();

            $table->unique(['user_id', 'customer_key']);
            $table->index(['user_id', 'is_returning_customer']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_customers');
    }
};
