<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ]
        );

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
