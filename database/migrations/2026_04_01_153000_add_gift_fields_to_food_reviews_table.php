<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_reviews', function (Blueprint $table) {
            $table->string('gift_code', 20)->nullable()->unique()->after('review_content');
            $table->string('gift_status', 30)->default('chua_thuong')->after('gift_code');
            $table->timestamp('gift_rendered_at')->nullable()->after('gift_status');
        });
    }

    public function down(): void
    {
        Schema::table('food_reviews', function (Blueprint $table) {
            $table->dropColumn(['gift_code', 'gift_status', 'gift_rendered_at']);
        });
    }
};

