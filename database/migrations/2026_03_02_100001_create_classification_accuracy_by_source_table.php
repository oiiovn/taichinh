<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classification_accuracy_by_source', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source', 32)->comment('rule, behavior, recurring, global, ai');
            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedInteger('wrong_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classification_accuracy_by_source');
    }
};
