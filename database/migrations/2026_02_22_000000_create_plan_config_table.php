<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_config', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique()->comment('VD: plans');
            $table->json('value')->comment('Cấu hình gói: term_months, term_options, order, list');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_config');
    }
};
