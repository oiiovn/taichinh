<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_reviews', function (Blueprint $table) {
            $table->boolean('rating_confirmed')->default(false)->after('rating');
        });

        DB::table('food_reviews')
            ->whereNotNull('raw_chunk')
            ->update(['rating_confirmed' => true]);

        DB::table('food_reviews')
            ->where('gift_verification_status', 'verified')
            ->update(['rating_confirmed' => true]);

        DB::table('food_reviews')
            ->whereNull('rating')
            ->where(function ($q) {
                $q->whereNotNull('gift_rendered_at')
                    ->orWhereNotNull('gift_verification_status');
            })
            ->update(['rating' => 5]);
    }

    public function down(): void
    {
        Schema::table('food_reviews', function (Blueprint $table) {
            $table->dropColumn('rating_confirmed');
        });
    }
};
