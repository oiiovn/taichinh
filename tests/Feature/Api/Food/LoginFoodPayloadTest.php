<?php

use App\Models\Employee;
use App\Models\FoodBranch;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesAttendanceSchema;

uses(CreatesAttendanceSchema::class);

beforeEach(function () {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('d', 32))]);
    $this->createAttendanceSchema();
});

test('login food staff trả food me-like payload', function () {
    $owner = User::query()->create([
        'name' => 'Owner',
        'email' => 'owner-login@test.local',
        'password' => Hash::make('password'),
    ]);
    $user = User::query()->create([
        'name' => 'Staff',
        'email' => 'staff-login@test.local',
        'password' => Hash::make('secret123'),
        'can_use_food_employee' => true,
        'can_use_qr_cham_cong' => true,
    ]);
    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'salary_type' => 'hour',
        'salary_rate' => 25000,
        'active' => true,
    ]);
    $branch = FoodBranch::query()->create([
        'user_id' => $owner->id,
        'name' => 'CN Login',
        'latitude' => 21.0,
        'longitude' => 105.0,
        'check_in_radius_meters' => 100,
    ]);
    $employee->foodBranches()->attach($branch->id, ['is_primary' => true]);

    $this->postJson('/api/v1/login', [
        'email' => 'staff-login@test.local',
        'password' => 'secret123',
    ])
        ->assertOk()
        ->assertJsonPath('user.email', 'staff-login@test.local')
        ->assertJsonPath('food.employee.id', $employee->id)
        ->assertJsonPath('food.permissions.can_use_food_employee', true)
        ->assertJsonPath('food.branches.0.id', $branch->id)
        ->assertJsonMissingPath('user.password');
});

test('login user không food không có key food', function () {
    User::query()->create([
        'name' => 'Plain',
        'email' => 'plain@test.local',
        'password' => Hash::make('secret123'),
        'can_use_food_employee' => false,
        'can_use_qr_cham_cong' => false,
    ]);

    $this->postJson('/api/v1/login', [
        'email' => 'plain@test.local',
        'password' => 'secret123',
    ])
        ->assertOk()
        ->assertJsonMissingPath('food');
});
