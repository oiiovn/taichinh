<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('behavior_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('suggestion_type', 64)->comment('reduce_expense, pay_debt, increase_income, ...');
            $table->boolean('accepted')->comment('true = đồng ý / đã làm, false = từ chối / không khả thi');
            $table->boolean('action_taken')->nullable()->comment('user có thực sự làm theo không');
            $table->timestamp('logged_at')->useCurrent();
            $table->timestamps();
        });

        Schema::table('behavior_logs', function (Blueprint $table) {
            $table->index(['user_id', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behavior_logs');
    }
};
