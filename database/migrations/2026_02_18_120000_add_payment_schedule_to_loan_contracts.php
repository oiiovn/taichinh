<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_contracts', function (Blueprint $table) {
            $table->boolean('payment_schedule_enabled')->default(false)->after('auto_accrue')
                ->comment('Bật tự tạo giao dịch chờ thanh toán khi đến ngày');
            $table->unsignedTinyInteger('payment_day_of_month')->nullable()->after('payment_schedule_enabled')
                ->comment('Ngày trong tháng trả (1-28, null = dùng due_date)');
        });
    }

    public function down(): void
    {
        Schema::table('loan_contracts', function (Blueprint $table) {
            $table->dropColumn(['payment_schedule_enabled', 'payment_day_of_month']);
        });
    }
};
