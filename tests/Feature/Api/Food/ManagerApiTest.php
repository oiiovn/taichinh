<?php

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\FoodBranch;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesAttendanceSchema;

uses(CreatesAttendanceSchema::class);

beforeEach(function () {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('e', 32))]);
    $this->createAttendanceSchema();
});

/**
 * @return array{manager: User, otherOwner: User, branch: FoodBranch, otherBranch: FoodBranch, employee: Employee, token: string}
 */
function managerFixture(): array
{
    $manager = User::query()->create([
        'name' => 'Manager',
        'email' => 'mgr-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'can_manage_food_cham_cong' => true,
    ]);
    $otherOwner = User::query()->create([
        'name' => 'OtherOwner',
        'email' => 'own-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
    ]);
    $staffUser = User::query()->create([
        'name' => 'Staff M',
        'email' => 'stf-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'can_use_food_employee' => true,
    ]);
    $employee = Employee::query()->create([
        'user_id' => $staffUser->id,
        'position' => 'PV',
        'salary_type' => 'hour',
        'salary_rate' => 20000,
        'active' => true,
    ]);
    $branch = FoodBranch::query()->create([
        'user_id' => $manager->id,
        'name' => 'CN Mgr',
        'latitude' => 21.0,
        'longitude' => 105.0,
        'check_in_radius_meters' => 100,
    ]);
    $otherBranch = FoodBranch::query()->create([
        'user_id' => $otherOwner->id,
        'name' => 'CN Other',
        'latitude' => 21.1,
        'longitude' => 105.1,
        'check_in_radius_meters' => 100,
    ]);
    $employee->foodBranches()->attach($branch->id, ['is_primary' => true]);

    AttendanceLog::query()->create([
        'employee_id' => $employee->id,
        'food_branch_id' => $branch->id,
        'work_date' => now()->toDateString(),
        'check_in_at' => now()->setTime(9, 0),
        'check_in_method' => 'qr',
    ]);

    $token = $manager->createToken('test')->plainTextToken;

    return compact('manager', 'otherOwner', 'branch', 'otherBranch', 'employee', 'token');
}

function mgrHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

test('manager unauthenticated → 401', function () {
    $this->getJson('/api/v1/food/manager/branches')->assertUnauthorized();
});

test('staff không có quyền manager → 403', function () {
    $user = User::query()->create([
        'name' => 'StaffOnly',
        'email' => 'staffonly@test.local',
        'password' => Hash::make('password'),
        'can_use_food_employee' => true,
        'can_manage_food_cham_cong' => false,
    ]);
    Employee::query()->create([
        'user_id' => $user->id,
        'salary_type' => 'hour',
        'salary_rate' => 1,
        'active' => true,
    ]);
    $token = $user->createToken('t')->plainTextToken;

    $this->withHeaders(mgrHeaders($token))
        ->getJson('/api/v1/food/manager/branches')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN_MANAGE_CHAM_CONG');
});

test('manager chỉ thấy branch của mình', function () {
    $fx = managerFixture();
    $res = $this->withHeaders(mgrHeaders($fx['token']))->getJson('/api/v1/food/manager/branches');
    $res->assertOk();
    $ids = collect($res->json('data'))->pluck('id')->all();
    expect($ids)->toContain($fx['branch']->id)
        ->and($ids)->not->toContain($fx['otherBranch']->id);
});

test('manager không truy cập branch owner khác', function () {
    $fx = managerFixture();
    $this->withHeaders(mgrHeaders($fx['token']))
        ->getJson('/api/v1/food/manager/attendance/today?branch_id='.$fx['otherBranch']->id)
        ->assertNotFound()
        ->assertJsonPath('error.code', 'BRANCH_NOT_FOUND');
});

test('manager today chỉ nhân viên thuộc CN mình', function () {
    $fx = managerFixture();
    $res = $this->withHeaders(mgrHeaders($fx['token']))
        ->getJson('/api/v1/food/manager/attendance/today?branch_id='.$fx['branch']->id);
    $res->assertOk()
        ->assertJsonPath('data.summary.total', 1)
        ->assertJsonPath('data.summary.checked_in', 1)
        ->assertJsonPath('data.items.0.employee.id', $fx['employee']->id)
        ->assertJsonPath('data.items.0.status', 'checked_in');
});

test('manager history không lộ NV chi nhánh khác', function () {
    $fx = managerFixture();
    $otherStaff = User::query()->create([
        'name' => 'OtherStaff',
        'email' => 'ost-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
    ]);
    $otherEmp = Employee::query()->create([
        'user_id' => $otherStaff->id,
        'salary_type' => 'hour',
        'salary_rate' => 1,
        'active' => true,
    ]);
    $otherEmp->foodBranches()->attach($fx['otherBranch']->id, ['is_primary' => true]);
    AttendanceLog::query()->create([
        'employee_id' => $otherEmp->id,
        'food_branch_id' => $fx['otherBranch']->id,
        'work_date' => now()->toDateString(),
        'check_in_at' => now(),
    ]);

    $res = $this->withHeaders(mgrHeaders($fx['token']))
        ->getJson('/api/v1/food/manager/attendance/history?from='.now()->toDateString().'&to='.now()->toDateString());
    $res->assertOk();
    $empIds = collect($res->json('data.items'))->pluck('employee.id')->all();
    expect($empIds)->toContain($fx['employee']->id)
        ->and($empIds)->not->toContain($otherEmp->id);
});

test('login manager trả food permissions manage', function () {
    User::query()->create([
        'name' => 'MgrLogin',
        'email' => 'mgrlogin@test.local',
        'password' => Hash::make('secret123'),
        'can_manage_food_cham_cong' => true,
    ]);

    $this->postJson('/api/v1/login', [
        'email' => 'mgrlogin@test.local',
        'password' => 'secret123',
    ])
        ->assertOk()
        ->assertJsonPath('food.permissions.can_manage_food_cham_cong', true)
        ->assertJsonPath('food.employee', null);
});
