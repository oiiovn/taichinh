<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tiền VND: lưu số nguyên đồng (DECIMAL(15,0)), không lưu dạng có dấu chấm phân cách hàng nghìn.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE food_products MODIFY gia_von DECIMAL(15, 0) UNSIGNED NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE food_sales_report_items MODIFY gia_von_unit DECIMAL(15, 0) UNSIGNED NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE food_products MODIFY gia_von DECIMAL(15, 4) UNSIGNED NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE food_sales_report_items MODIFY gia_von_unit DECIMAL(15, 4) UNSIGNED NOT NULL DEFAULT 0');
    }
};
