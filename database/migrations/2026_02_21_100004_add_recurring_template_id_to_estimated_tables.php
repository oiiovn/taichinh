<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimated_incomes', function (Blueprint $table) {
            $table->foreignId('recurring_template_id')->nullable()->after('mode')->constrained('estimated_recurring_templates')->nullOnDelete();
        });
        Schema::table('estimated_expenses', function (Blueprint $table) {
            $table->foreignId('recurring_template_id')->nullable()->after('mode')->constrained('estimated_recurring_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('estimated_incomes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurring_template_id');
        });
        Schema::table('estimated_expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurring_template_id');
        });
    }
};
