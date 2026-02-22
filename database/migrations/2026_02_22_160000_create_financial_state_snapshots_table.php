<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_state_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_date')->comment('Ngày kỳ snapshot');
            $table->json('structural_state')->nullable()->comment('Pha/state (key, label, ...)');
            $table->unsignedTinyInteger('buffer_months')->nullable()->comment('Runway từ thanh khoản thực tế');
            $table->unsignedTinyInteger('recommended_buffer')->nullable()->comment('Buffer khuyến nghị (context-aware)');
            $table->unsignedTinyInteger('dsi')->nullable()->comment('Debt Stress Index 0-100');
            $table->decimal('debt_exposure', 18, 2)->nullable();
            $table->decimal('receivable_exposure', 18, 2)->nullable();
            $table->decimal('net_leverage', 18, 2)->nullable();
            $table->decimal('income_volatility', 8, 4)->nullable()->comment('volatility_ratio');
            $table->decimal('spending_discipline_score', 5, 2)->nullable()->comment('0-1');
            $table->json('objective')->nullable()->comment('Mục tiêu (key, label)');
            $table->json('priority_alignment')->nullable()->comment('aligned, suggested_direction, ...');
            $table->string('narrative_hash', 64)->nullable()->comment('insightHash của kỳ');
            $table->unsignedInteger('total_feedback_count')->default(0)->comment('Số lần phản hồi tích lũy (cảnh báo không thay đổi)');
            $table->timestamps();
        });

        Schema::table('financial_state_snapshots', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_state_snapshots');
    }
};
