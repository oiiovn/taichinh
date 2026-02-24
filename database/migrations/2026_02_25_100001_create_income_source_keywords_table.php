<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('income_source_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('income_source_id')->constrained('user_income_sources')->cascadeOnDelete();
            $table->string('keyword', 255);
            $table->string('match_type', 20)->default('contains'); // contains | exact | regex
            $table->decimal('weight', 5, 4)->nullable()->default(1);
            $table->timestamps();
        });

        Schema::table('income_source_keywords', function (Blueprint $table) {
            $table->index('income_source_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('income_source_keywords');
    }
};
