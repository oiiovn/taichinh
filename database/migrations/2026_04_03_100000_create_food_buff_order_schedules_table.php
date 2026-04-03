<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('food_buff_order_schedules')) {
            Schema::create('food_buff_order_schedules', function (Blueprint $table) {
                $table->id();
                $table->date('schedule_date');
                $table->json('branch_targets');
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('food_buff_order_schedule_user')) {
            Schema::create('food_buff_order_schedule_user', function (Blueprint $table) {
                $table->unsignedBigInteger('food_buff_order_schedule_id');
                $table->unsignedBigInteger('user_id');
                $table->primary(['food_buff_order_schedule_id', 'user_id'], 'food_buff_schedule_user_primary');
                $table->foreign('food_buff_order_schedule_id', 'fb_os_user_sched_fk')
                    ->references('id')->on('food_buff_order_schedules')->cascadeOnDelete();
                $table->foreign('user_id', 'fb_os_user_user_fk')
                    ->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('food_buff_order_schedule_acknowledgments')) {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('food_buff_order_schedule_acknowledgments');
        Schema::dropIfExists('food_buff_order_schedule_user');
        Schema::dropIfExists('food_buff_order_schedules');
    }
};
