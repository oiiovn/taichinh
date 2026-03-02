<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('global_merchant_patterns', function (Blueprint $table) {
            $table->unsignedInteger('correct_count')->default(0)->after('usage_count');
            $table->unsignedInteger('wrong_count')->default(0)->after('correct_count');
            $table->timestamp('last_used_at')->nullable()->after('wrong_count');
            $table->timestamp('last_wrong_at')->nullable()->after('last_used_at');
            $table->decimal('decay_factor', 5, 4)->nullable()->after('last_wrong_at');
        });
    }

    public function down(): void
    {
        Schema::table('global_merchant_patterns', function (Blueprint $table) {
            $table->dropColumn(['correct_count', 'wrong_count', 'last_used_at', 'last_wrong_at', 'decay_factor']);
        });
    }
};
