<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema tối thiểu cho attendance tests trên SQLite :memory:
 * (tránh RefreshDatabase full — một số migration MySQL-only).
 */
trait CreatesAttendanceSchema
{
    protected function createAttendanceSchema(): void
    {
        Schema::dropIfExists('attendance_logs');
        Schema::dropIfExists('employee_food_branch');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('food_branches');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->boolean('can_use_food_employee')->default(false);
            $table->boolean('can_use_qr_cham_cong')->default(false);
            $table->boolean('can_manage_food_employees')->default(false);
            $table->boolean('can_manage_food_cham_cong')->default(false);
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('position')->nullable();
            $table->string('salary_type', 20)->default('hour');
            $table->decimal('salary_rate', 14, 2)->default(0);
            $table->date('start_date')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('apply_late_penalty')->default(false);
            $table->time('shift_start_time')->nullable();
            $table->timestamps();
            $table->unique('user_id');
        });

        Schema::create('food_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('address', 500)->nullable();
            $table->string('branch_link', 255)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('check_in_radius_meters')->default(100);
            $table->timestamps();
        });

        Schema::create('employee_food_branch', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('food_branch_id')->constrained('food_branches')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['employee_id', 'food_branch_id']);
        });

        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('food_branch_id')->nullable()->constrained('food_branches')->nullOnDelete();
            $table->date('work_date');
            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_out_at')->nullable();
            $table->dateTime('break_start_at')->nullable();
            $table->dateTime('break_end_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->decimal('check_in_latitude', 10, 7)->nullable();
            $table->decimal('check_in_longitude', 10, 7)->nullable();
            $table->decimal('check_out_latitude', 10, 7)->nullable();
            $table->decimal('check_out_longitude', 10, 7)->nullable();
            $table->string('check_in_method', 16)->nullable();
            $table->string('check_out_method', 16)->nullable();
            $table->unsignedInteger('check_in_distance_meters')->nullable();
            $table->unsignedInteger('check_out_distance_meters')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'work_date']);
        });
    }
}
