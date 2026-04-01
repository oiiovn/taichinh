<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('food_branch_id')->nullable()->constrained('food_branches')->nullOnDelete();
            $table->string('review_code', 80)->unique();
            $table->date('review_date')->nullable();
            $table->string('review_time_text', 80)->nullable();
            $table->string('customer_name', 255)->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('review_content')->nullable();
            $table->text('raw_chunk')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'review_date']);
            $table->index(['food_branch_id', 'review_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_reviews');
    }
};

