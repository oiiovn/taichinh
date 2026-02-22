<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_insight_ai_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('snapshot_hash', 64)->comment('Hash của trạng thái phân tích để tránh gọi GPT trùng');
            $table->text('narrative')->comment('Narrative do GPT synthesis trả về');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::table('financial_insight_ai_cache', function (Blueprint $table) {
            $table->index(['user_id', 'snapshot_hash']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_insight_ai_cache');
    }
};
