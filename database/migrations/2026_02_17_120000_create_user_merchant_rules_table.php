<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_merchant_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('merchant_key', 255);
            $table->foreignId('mapped_user_category_id')->constrained('user_categories')->cascadeOnDelete();
            $table->unsignedInteger('confirmed_count')->default(0);
            $table->float('confidence_score')->default(0);
            $table->timestamp('last_confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('user_merchant_rules', function (Blueprint $table) {
            $table->unique(['user_id', 'merchant_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_merchant_rules');
    }
};
