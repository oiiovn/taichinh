<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_recurring_patterns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('merchant_group', 255);
            $table->string('direction', 10);
            $table->decimal('avg_amount', 15, 2);
            $table->decimal('amount_std', 15, 2)->default(0);
            $table->decimal('avg_interval_days', 8, 2);
            $table->decimal('interval_std', 8, 2)->default(0);
            $table->foreignId('user_category_id')->nullable()->constrained('user_categories')->nullOnDelete();
            $table->float('confidence_score')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->date('next_expected_at')->nullable();
            $table->string('status', 20)->default('active')->comment('active, weak, broken');
            $table->unsignedInteger('match_count')->default(0);
            $table->timestamps();
        });

        Schema::table('user_recurring_patterns', function (Blueprint $table) {
            $table->index(['user_id', 'merchant_group', 'direction']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_recurring_patterns');
    }
};
