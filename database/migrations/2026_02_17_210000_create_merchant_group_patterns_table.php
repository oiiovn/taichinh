<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_group_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->nullable()->comment('Nhãn mô tả pattern');
            $table->string('pattern_type', 20)->default('regex')->comment('regex, contains, starts_with');
            $table->string('pattern', 500)->comment('Regex hoặc chuỗi tìm kiếm');
            $table->string('merchant_key', 255)->comment('Key ổn định khi match (vd: rz_gd, bank_ref)');
            $table->string('merchant_group', 255)->comment('Nhóm cho behavior/global (vd: bank_ref)');
            $table->unsignedSmallInteger('priority')->default(0)->comment('Cao hơn = ưu tiên trước');
            $table->boolean('is_active')->default(true);
            $table->json('match_conditions')->nullable()->comment('VD: {"min_length": 20}');
            $table->timestamps();
        });

        Schema::table('merchant_group_patterns', function (Blueprint $table) {
            $table->index(['is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_group_patterns');
    }
};
