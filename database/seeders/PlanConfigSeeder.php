<?php

namespace Database\Seeders;

use App\Models\PlanConfig;
use Illuminate\Database\Seeder;

class PlanConfigSeeder extends Seeder
{
    public function run(): void
    {
        if (PlanConfig::where('key', PlanConfig::CONFIG_KEY)->exists()) {
            return;
        }
        $config = config('plans', PlanConfig::defaultConfig());
        PlanConfig::create(['key' => PlanConfig::CONFIG_KEY, 'value' => $config]);
        PlanConfig::clearCache();
    }
}
