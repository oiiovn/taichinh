<?php

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\FoodBranch;
use App\Models\User;
use App\Services\Food\QrAttendanceToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesAttendanceSchema;

uses(CreatesAttendanceSchema::class);

beforeEach(function () {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('c', 32))]);
    $this->createAttendanceSchema();
    Carbon::setTestNow(Carbon::parse('2026-07-30 14:30:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * @return array{user: User, employee: Employee, branch: FoodBranch, owner: User, token: string}
 */
function apiStaff(array $overrides = []): array
{
    $owner = User::query()->create([
        'name' => 'Owner',
        'email' => 'owner-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'is_admin' => false,
        'can_use_food_employee' => false,
        'can_use_qr_cham_cong' => false,
    ]);

    $user = User::query()->create([
        'name' => 'Staff',
        'email' => 'staff-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'is_admin' => false,
        'can_use_food_employee' => $overrides['can_use_food_employee'] ?? true,
        'can_use_qr_cham_cong' => $overrides['can_use_qr_cham_cong'] ?? true,
    ]);

    $employee = null;
    if ($overrides['with_employee'] ?? true) {
        $employee = Employee::query()->create([
            'user_id' => $user->id,
            'position' => 'NV',
            'salary_type' => 'hour',
            'salary_rate' => 50000,
            'active' => $overrides['active'] ?? true,
            'apply_late_penalty' => false,
        ]);
    }

    $branch = FoodBranch::query()->create([
        'user_id' => $owner->id,
        'name' => 'CN A',
        'address' => 'HN',
        'latitude' => $overrides['latitude'] ?? 21.0285110,
        'longitude' => $overrides['longitude'] ?? 105.8048170,
        'check_in_radius_meters' => $overrides['radius'] ?? 100,
    ]);

    if ($employee && ($overrides['assign'] ?? true)) {
        $employee->foodBranches()->attach($branch->id, ['is_primary' => true]);
    }

    $plain = $user->createToken('test')->plainTextToken;

    return compact('user', 'employee', 'branch', 'owner') + ['token' => $plain];
}

function authHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

function checkPayload(FoodBranch $branch, ?string $qrToken = null, ?float $lat = null, ?float $lng = null): array
{
    return [
        'branch_id' => $branch->id,
        'qr_token' => $qrToken ?? QrAttendanceToken::makeForBranch($branch->id),
        'latitude' => $lat ?? 21.0285110,
        'longitude' => $lng ?? 105.8048170,
    ];
}

test('unauthenticated → 401', function () {
    $this->getJson('/api/v1/food/me')->assertUnauthorized();
});

test('user không có employee → 403', function () {
    $fx = apiStaff(['with_employee' => false]);
    $this->withHeaders(authHeaders($fx['token']))
        ->getJson('/api/v1/food/me')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'EMPLOYEE_NOT_FOUND');
});

test('employee inactive → 403', function () {
    $fx = apiStaff(['active' => false]);
    $this->withHeaders(authHeaders($fx['token']))
        ->getJson('/api/v1/food/me')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'EMPLOYEE_INACTIVE');
});

test('không có can_use_food_employee → 403', function () {
    $fx = apiStaff(['can_use_food_employee' => false]);
    $this->withHeaders(authHeaders($fx['token']))
        ->getJson('/api/v1/food/me')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN_FOOD_EMPLOYEE');
});

test('không có can_use_qr_cham_cong → 403 trên check-in', function () {
    $fx = apiStaff(['can_use_qr_cham_cong' => false]);
    $this->withHeaders(authHeaders($fx['token']))
        ->postJson('/api/v1/food/attendance/check-in', checkPayload($fx['branch']))
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN_QR_CHAM_CONG');
});

test('branch chưa assign → 403', function () {
    $fx = apiStaff(['assign' => false]);
    $this->withHeaders(authHeaders($fx['token']))
        ->postJson('/api/v1/food/attendance/check-in', checkPayload($fx['branch']))
        ->assertForbidden()
        ->assertJsonPath('error.code', 'BRANCH_NOT_ASSIGNED');
});

test('invalid QR → 422', function () {
    $fx = apiStaff();
    $this->withHeaders(authHeaders($fx['token']))
        ->postJson('/api/v1/food/attendance/check-in', checkPayload($fx['branch'], 'invalid-token'))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'INVALID_QR');
});

test('expired QR → 422', function () {
    $fx = apiStaff();
    $token = QrAttendanceToken::makeForBranch($fx['branch']->id, now()->subMinutes(3));
    $this->withHeaders(authHeaders($fx['token']))
        ->postJson('/api/v1/food/attendance/check-in', checkPayload($fx['branch'], $token))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'EXPIRED_QR');
});

test('GPS invalid (validation) → 422', function () {
    $fx = apiStaff();
    $payload = checkPayload($fx['branch']);
    $payload['latitude'] = 99;
    $this->withHeaders(authHeaders($fx['token']))
        ->postJson('/api/v1/food/attendance/check-in', $payload)
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

test('GPS ngoài radius → 422', function () {
    $fx = apiStaff(['radius' => 50]);
    $this->withHeaders(authHeaders($fx['token']))
        ->postJson('/api/v1/food/attendance/check-in', checkPayload($fx['branch'], null, 21.0385110, 105.8048170))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'OUTSIDE_BRANCH_RADIUS');
});

test('legacy QR không được mobile API chấp nhận', function () {
    $fx = apiStaff();
    $legacy = QrAttendanceToken::makeLegacy();
    $this->withHeaders(authHeaders($fx['token']))
        ->postJson('/api/v1/food/attendance/check-in', checkPayload($fx['branch'], $legacy))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'INVALID_QR');
});

test('check-in thành công → 201', function () {
    $fx = apiStaff();
    $this->withHeaders(authHeaders($fx['token']))
        ->postJson('/api/v1/food/attendance/check-in', checkPayload($fx['branch']))
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.attendance.status', 'checked_in')
        ->assertJsonPath('data.attendance.branch.id', $fx['branch']->id)
        ->assertJsonPath('data.attendance.check_in_method', 'qr');
});

test('duplicate check-in → 409', function () {
    $fx = apiStaff();
    $headers = authHeaders($fx['token']);
    $this->withHeaders($headers)->postJson('/api/v1/food/attendance/check-in', checkPayload($fx['branch']))->assertCreated();
    $this->withHeaders($headers)
        ->postJson('/api/v1/food/attendance/check-in', checkPayload($fx['branch']))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'ALREADY_CHECKED_IN');
});

test('check-out trước check-in → 409', function () {
    $fx = apiStaff();
    $this->withHeaders(authHeaders($fx['token']))
        ->postJson('/api/v1/food/attendance/check-out', checkPayload($fx['branch']))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'NOT_CHECKED_IN');
});

test('check-out thành công và duplicate → 409', function () {
    $fx = apiStaff();
    $headers = authHeaders($fx['token']);
    $this->withHeaders($headers)->postJson('/api/v1/food/attendance/check-in', checkPayload($fx['branch']))->assertCreated();

    Carbon::setTestNow(Carbon::parse('2026-07-30 18:00:00'));
    $this->withHeaders($headers)
        ->postJson('/api/v1/food/attendance/check-out', checkPayload($fx['branch']))
        ->assertOk()
        ->assertJsonPath('data.attendance.status', 'checked_out')
        ->assertJsonPath('data.attendance.check_out_method', 'qr');

    $this->withHeaders($headers)
        ->postJson('/api/v1/food/attendance/check-out', checkPayload($fx['branch']))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'ALREADY_CHECKED_OUT');
});

test('checkout khác branch → 409', function () {
    $fx = apiStaff();
    $other = FoodBranch::query()->create([
        'user_id' => $fx['owner']->id,
        'name' => 'CN B',
        'latitude' => 21.0285110,
        'longitude' => 105.8048170,
        'check_in_radius_meters' => 100,
    ]);
    $fx['employee']->foodBranches()->attach($other->id, ['is_primary' => false]);

    $headers = authHeaders($fx['token']);
    $this->withHeaders($headers)->postJson('/api/v1/food/attendance/check-in', checkPayload($fx['branch']))->assertCreated();

    Carbon::setTestNow(Carbon::parse('2026-07-30 18:00:00'));
    $this->withHeaders($headers)
        ->postJson('/api/v1/food/attendance/check-out', checkPayload($other))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'BRANCH_NOT_ASSIGNED');
});

test('today chỉ trả employee hiện tại', function () {
    $fx = apiStaff();
    $otherUser = User::query()->create([
        'name' => 'Other',
        'email' => 'other-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'can_use_food_employee' => true,
        'can_use_qr_cham_cong' => true,
    ]);
    $otherEmp = Employee::query()->create([
        'user_id' => $otherUser->id,
        'salary_type' => 'hour',
        'salary_rate' => 1,
        'active' => true,
    ]);
    AttendanceLog::query()->create([
        'employee_id' => $otherEmp->id,
        'work_date' => now()->toDateString(),
        'check_in_at' => now(),
        'food_branch_id' => $fx['branch']->id,
    ]);

    $this->withHeaders(authHeaders($fx['token']))
        ->postJson('/api/v1/food/attendance/check-in', checkPayload($fx['branch']))
        ->assertCreated();

    $res = $this->withHeaders(authHeaders($fx['token']))->getJson('/api/v1/food/attendance/today');
    $res->assertOk()
        ->assertJsonPath('data.attendance.status', 'checked_in');
    expect(AttendanceLog::query()->count())->toBe(2);
});

test('history chỉ trả employee hiện tại', function () {
    $fx = apiStaff();
    $headers = authHeaders($fx['token']);
    $this->withHeaders($headers)->postJson('/api/v1/food/attendance/check-in', checkPayload($fx['branch']))->assertCreated();

    $otherUser = User::query()->create([
        'name' => 'Other2',
        'email' => 'other2-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'can_use_food_employee' => true,
        'can_use_qr_cham_cong' => true,
    ]);
    $otherEmp = Employee::query()->create([
        'user_id' => $otherUser->id,
        'salary_type' => 'hour',
        'salary_rate' => 1,
        'active' => true,
    ]);
    AttendanceLog::query()->create([
        'employee_id' => $otherEmp->id,
        'work_date' => now()->toDateString(),
        'check_in_at' => now(),
    ]);

    $res = $this->withHeaders($headers)->getJson('/api/v1/food/attendance/history?from=2026-07-01&to=2026-07-31');
    $res->assertOk();
    $items = $res->json('data.items');
    expect($items)->toHaveCount(1);
});

test('branch list chỉ trả branch được assign', function () {
    $fx = apiStaff();
    FoodBranch::query()->create([
        'user_id' => $fx['owner']->id,
        'name' => 'CN Hidden',
        'latitude' => 21.0,
        'longitude' => 105.0,
        'check_in_radius_meters' => 100,
    ]);

    $res = $this->withHeaders(authHeaders($fx['token']))->getJson('/api/v1/food/branches');
    $res->assertOk();
    $data = $res->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['id'])->toBe($fx['branch']->id)
        ->and($data[0]['is_primary'])->toBeTrue();
});

test('GET me trả user employee permissions branches', function () {
    $fx = apiStaff();
    $this->withHeaders(authHeaders($fx['token']))
        ->getJson('/api/v1/food/me')
        ->assertOk()
        ->assertJsonPath('data.user.id', $fx['user']->id)
        ->assertJsonPath('data.employee.id', $fx['employee']->id)
        ->assertJsonPath('data.permissions.can_use_food_employee', true)
        ->assertJsonPath('data.permissions.can_use_qr_cham_cong', true)
        ->assertJsonMissingPath('data.user.password');
});
