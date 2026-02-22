<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimated_incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('income_sources')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 15, 0)->unsigned()->default(0);
            $table->string('note')->nullable();
            $table->string('mode', 20)->default('manual'); // manual | recurring
            $table->timestamps();

            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimated_incomes');
    }
};
