<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_sales_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('report_code', 20)->index();
            $table->date('report_date');
            $table->unsignedInteger('total_orders')->default(0);
            $table->decimal('total_cost', 15, 4)->unsigned()->default(0);
            $table->decimal('total_tien_cong', 15, 0)->unsigned()->default(0);
            $table->dateTime('uploaded_at');
            $table->timestamps();

            $table->index(['user_id', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_sales_reports');
    }
};
