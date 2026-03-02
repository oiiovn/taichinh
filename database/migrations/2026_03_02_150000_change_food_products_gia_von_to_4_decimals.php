<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE food_products MODIFY gia_von DECIMAL(15, 4) UNSIGNED NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE food_products MODIFY gia_von DECIMAL(15, 2) UNSIGNED NOT NULL DEFAULT 0');
    }
};
