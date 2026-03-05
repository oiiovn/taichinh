<?php

namespace Database\Seeders;

use App\Models\FoodReportBonusTier;
use Illuminate\Database\Seeder;

class FoodReportBonusTierSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['min_total_cost' => 400000, 'bonus_amount' => 60000, 'sort_order' => 1],
            ['min_total_cost' => 600000, 'bonus_amount' => 80000, 'sort_order' => 2],
        ];

        foreach ($defaults as $row) {
            FoodReportBonusTier::firstOrCreate(
                ['min_total_cost' => $row['min_total_cost']],
                ['bonus_amount' => $row['bonus_amount'], 'sort_order' => $row['sort_order']]
            );
        }
    }
}
