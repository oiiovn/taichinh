<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('behavior_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable()->comment('Nếu null dùng start_date + duration_days');
            $table->unsignedSmallInteger('duration_days')->nullable()->comment('Số ngày chương trình');
            $table->string('archetype', 30)->default('binary')->comment('binary, quantitative, avoidance, progressive, identity');
            $table->unsignedTinyInteger('difficulty_level')->default(1)->comment('1-5');
            $table->string('validation_strategy', 80)->nullable()->comment('Cách đánh giá hoàn thành ngày');
            $table->string('escalation_rule', 80)->nullable()->comment('abandon, allow_2_skips, reduce_difficulty');
            $table->string('status', 20)->default('active')->comment('active, completed, failed');
            $table->unsignedTinyInteger('daily_target_count')->nullable()->comment('Mỗi ngày bao nhiêu task (cho quantitative)');
            $table->string('skip_policy', 40)->nullable()->comment('UI: bỏ luôn / cho phép 2 lần / giảm độ khó');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behavior_programs');
    }
};
