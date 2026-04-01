<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_buff_orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('review_rating')->nullable()->after('customer_reviewed');
            $table->text('review_content')->nullable()->after('review_rating');
        });
    }

    public function down(): void
    {
        Schema::table('food_buff_orders', function (Blueprint $table) {
            $table->dropColumn(['review_rating', 'review_content']);
        });
    }
};
