<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(SystemCategorySeeder::class);
        $this->call(MerchantGroupPatternSeeder::class);
        $this->call(FinancialIntelligenceDatasetSeeder::class);
        $this->call(LowIncomeHighDebtDemoSeeder::class);
        $this->call(MediumIncomeFoodBusinessDemoSeeder::class);
        $this->call(HighIncomeDemoSeeder::class);
        $this->call(BrainFourProfilesSeeder::class);
        $this->call(SimulationPersonaSeeder::class);
        $this->call(CongViecFourStagesSeeder::class);
        $this->call(PlanConfigSeeder::class);
    }
}
