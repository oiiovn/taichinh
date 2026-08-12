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
            $table->string('gift_verification_status', 20)->nullable()->after('gift_rendered_at');
            $table->timestamp('gift_verified_at')->nullable()->after('gift_verification_status');
            $table->timestamp('gift_revoked_at')->nullable()->after('gift_verified_at');
        });

        // Bản ghi đã phát quà trước đây (chỉ khi rating=5) → coi là đã xác minh.
        DB::table('food_reviews')
            ->whereNotNull('gift_rendered_at')
            ->where('rating', 5)
            ->update([
                'gift_verification_status' => 'verified',
                'gift_verified_at' => DB::raw('COALESCE(gift_rendered_at, NOW())'),
            ]);

        DB::table('food_reviews')
            ->whereNotNull('gift_rendered_at')
            ->where(function ($q) {
                $q->whereNull('rating')->orWhere('rating', '!=', 5);
            })
            ->update([
                'gift_verification_status' => 'pending',
            ]);
    }

    public function down(): void
    {
        Schema::table('food_reviews', function (Blueprint $table) {
            $table->dropColumn(['gift_verification_status', 'gift_verified_at', 'gift_revoked_at']);
        });
    }
};
