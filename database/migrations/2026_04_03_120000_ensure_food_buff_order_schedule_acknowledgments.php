<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('food_buff_order_schedule_acknowledgments')) {
            return;
        }

        if (! Schema::hasTable('food_buff_order_schedules')) {
            return;
        }

        Schema::create('food_buff_order_schedule_acknowledgments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('food_buff_order_schedule_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('acknowledged_at');
            $table->timestamps();
            $table->unique(['food_buff_order_schedule_id', 'user_id'], 'food_buff_schedule_ack_unique');
            $table->foreign('food_buff_order_schedule_id', 'fb_os_ack_sched_fk')
                ->references('id')->on('food_buff_order_schedules')->cascadeOnDelete();
            $table->foreign('user_id', 'fb_os_ack_user_fk')
                ->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_buff_order_schedule_acknowledgments');
    }
};
