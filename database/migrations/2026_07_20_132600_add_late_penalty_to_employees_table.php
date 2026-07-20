<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('apply_late_penalty')->default(false)->after('active');
            $table->time('shift_start_time')->nullable()->after('apply_late_penalty');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['apply_late_penalty', 'shift_start_time']);
        });
    }
};
