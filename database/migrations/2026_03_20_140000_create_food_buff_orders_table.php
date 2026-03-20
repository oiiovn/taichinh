<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_buff_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('food_branch_id')->nullable()->constrained('food_branches')->nullOnDelete();
            $table->string('invoice_code', 80);
            $table->date('order_date');
            $table->string('order_time_text', 50)->nullable();
            $table->string('receiver_name', 255)->nullable();
            $table->string('customer_name', 255)->nullable();
            $table->decimal('buff_amount', 15, 0)->default(20000);
            $table->decimal('labor_amount', 15, 0)->default(10000);
            $table->timestamps();

            $table->index(['user_id', 'order_date']);
            $table->unique(['user_id', 'invoice_code', 'order_date'], 'food_buff_orders_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_buff_orders');
    }
};
