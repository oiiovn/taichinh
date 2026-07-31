<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_reviews', function (Blueprint $table) {
            $table->foreignId('gift_rewarded_by_user_id')->nullable()->after('gift_rendered_at')->constrained('users')->nullOnDelete();
            $table->timestamp('gift_rewarded_at')->nullable()->after('gift_rewarded_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('food_reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gift_rewarded_by_user_id');
            $table->dropColumn('gift_rewarded_at');
        });
    }
};
