<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_merchant_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('merchant_key', 255)->unique();
            $table->foreignId('system_category_id')->constrained('system_categories')->cascadeOnDelete();
            $table->unsignedInteger('usage_count')->default(0);
            $table->float('confidence_score')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_merchant_patterns');
    }
};
