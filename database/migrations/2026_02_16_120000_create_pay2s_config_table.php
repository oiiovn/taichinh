<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay2s_config', function (Blueprint $table) {
            $table->id();
            $table->string('bank_id', 20)->nullable()->comment('Mã ngân hàng VietQR (VD: 970422)');
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable()->comment('Số tài khoản ngân hàng admin');
            $table->string('account_holder')->nullable()->comment('Chủ tài khoản');
            $table->string('branch')->nullable()->comment('Chi nhánh');
            $table->string('qr_template', 20)->default('compact')->comment('compact | compact2 | qr_only');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay2s_config');
    }
};
