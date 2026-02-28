<?php

namespace Database\Seeders;

use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Seeder;

class HouseholdUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'giadinh@gmail.com'],
            [
                'name' => 'Gia Đình',
                'password' => 'giadinh@gmail.com',
            ]
        );
        if (Household::where('owner_user_id', $user->id)->doesntExist()) {
            $household = Household::create(['name' => 'Gia Đình', 'owner_user_id' => $user->id]);
            $household->members()->create(['user_id' => $user->id, 'role' => 'owner']);
        }
    }
}
