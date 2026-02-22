<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('plan', 50)->nullable()->after('is_admin')->comment('Gói đang dùng: basic, starter, pro, ...');
            $table->dateTime('plan_expires_at')->nullable()->after('plan')->comment('Hết hạn gói (3 tháng từ ngày thanh toán)');
        });

        Schema::create('package_payment_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan_key', 50)->comment('basic, starter, pro, ...');
            $table->string('mapping_code', 100)->unique()->comment('PAY-{id}-TÊNGÓI, user chuyển khoản ghi nội dung này');
            $table->unsignedBigInteger('amount')->comment('Số tiền gói (price * term_months)');
            $table->string('status', 20)->default('pending')->comment('pending | paid');
            $table->foreignId('transaction_history_id')->nullable()->constrained('transaction_history')->nullOnDelete();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'mapping_code']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['plan', 'plan_expires_at']);
        });
        Schema::dropIfExists('package_payment_mappings');
    }
};
