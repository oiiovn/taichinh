<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 20)->comment('income | expense');
            $table->foreignId('based_on_system_category_id')->nullable()->constrained('system_categories')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('user_categories', function (Blueprint $table) {
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_categories');
    }
};
