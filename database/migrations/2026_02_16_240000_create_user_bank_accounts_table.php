<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('bank_code', 50)->comment('BIDV, ACB, MB, Vietcombank, VietinBank');
            $table->string('account_type', 20)->default('ca_nhan')->comment('ca_nhan, doanh_nghiep');
            $table->string('api_type', 20)->default('openapi')->comment('openapi, pay2s');
            $table->string('full_name')->nullable()->comment('Họ tên / tên công ty không dấu');
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('id_number', 30)->nullable()->comment('Số CCCD');
            $table->string('account_number', 50)->nullable()->comment('Số tài khoản ngân hàng');
            $table->string('virtual_account_prefix', 20)->nullable()->comment('VD: 963869 BIDV');
            $table->string('virtual_account_suffix', 50)->nullable();
            $table->string('company_name')->nullable();
            $table->string('login_username')->nullable()->comment('Tên đăng nhập ngân hàng');
            $table->text('login_password')->nullable()->comment('Mật khẩu mã hóa');
            $table->string('tax_code', 30)->nullable()->comment('Mã số thuế');
            $table->string('transaction_type', 20)->nullable()->comment('all, ...');
            $table->string('company_code', 50)->nullable()->comment('Mã doanh nghiệp');
            $table->boolean('agreed_terms')->default(false);
            $table->string('external_id')->nullable()->comment('ID từ Pay2s sau đồng bộ');
            $table->timestamp('last_synced_at')->nullable();
            $table->json('raw_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_bank_accounts');
    }
};
