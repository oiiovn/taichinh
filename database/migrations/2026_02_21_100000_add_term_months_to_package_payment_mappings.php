<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_payment_mappings', function (Blueprint $table) {
            $table->unsignedTinyInteger('term_months')->default(3)->after('amount')->comment('Kỳ hạn đã chọn: 3, 6, 12 tháng');
        });
    }

    public function down(): void
    {
        Schema::table('package_payment_mappings', function (Blueprint $table) {
            $table->dropColumn('term_months');
        });
    }
};
