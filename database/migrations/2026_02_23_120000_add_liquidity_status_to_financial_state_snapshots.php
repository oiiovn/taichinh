<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_state_snapshots', function (Blueprint $table) {
            $table->string('liquidity_status', 20)->nullable()->after('recommended_buffer')
                ->comment('positive, verified_zero, unknown — dùng để phân biệt verified crisis vs chưa có TK liên kết');
        });
    }

    public function down(): void
    {
        Schema::table('financial_state_snapshots', function (Blueprint $table) {
            $table->dropColumn('liquidity_status');
        });
    }
};
