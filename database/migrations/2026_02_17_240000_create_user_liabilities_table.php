<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_liabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('principal', 18, 2)->default(0);
            $table->decimal('interest_rate', 10, 4)->default(0)->comment('VD: 12 = 12%');
            $table->string('interest_unit', 20)->default('yearly')->comment('yearly|monthly|daily');
            $table->string('interest_calculation', 20)->default('simple')->comment('simple|compound');
            $table->string('accrual_frequency', 20)->default('daily')->comment('daily|weekly|monthly');
            $table->date('start_date');
            $table->date('due_date')->nullable();
            $table->boolean('auto_accrue')->default(true);
            $table->string('status', 20)->default('active')->comment('active|closed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_liabilities');
    }
};
