<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20)->comment('income | expense');
            $table->foreignId('parent_id')->nullable()->constrained('system_categories')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_categories');
    }
};
