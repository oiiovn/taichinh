<?php

use App\Exceptions\Food\AttendanceException;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\FoodBranch;
use App\Models\User;
use App\Services\Food\AttendanceService;
use App\Services\Food\QrAttendanceToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesAttendanceSchema;
use Tests\TestCase;

uses(TestCase::class, CreatesAttendanceSchema::class);

beforeEach(function () {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('b', 32))]);
    $this->createAttendanceSchema();
});

/**
 * @return array{user: User, employee: Employee, branch: FoodBranch, owner: User}
 */
function attendanceFixture(array $overrides = []): array
{
    $owner = User::query()->create([
        'name' => 'Owner',
        'email' => 'owner-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
    ]);

    $user = User::query()->create([
        'name' => 'Staff',
        'email' => 'staff-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'position' => 'NV',
        'salary_type' => 'hour',
        'salary_rate' => 50000,
        'active' => $overrides['active'] ?? true,
        'apply_late_penalty' => false,
    ]);

    $branch = FoodBranch::query()->create([
        'user_id' => $owner->id,
        'name' => 'CN Test',
        'address' => 'HN',
        'latitude' => $overrides['latitude'] ?? 21.0285110,
        'longitude' => $overrides['longitude'] ?? 105.8048170,
        'check_in_radius_meters' => $overrides['radius'] ?? 100,
    ]);

    if ($overrides['assign'] ?? true) {
        $employee->foodBranches()->attach($branch->id, ['is_primary' => true]);
    }

    $user->setRelation('employee', $employee);

    return compact('user', 'employee', 'branch', 'owner');
}

test('employee chưa assign branch bị reject', function () {
    $fx = attendanceFixture(['assign' => false]);
    $at = Carbon::parse('2026-07-30 14:30:00');
    $token = QrAttendanceToken::makeForBranch($fx['branch']->id, $at);
    $service = app(AttendanceService::class);

    try {
        $service->checkIn($fx['user'], $fx['branch']->id, $token, 21.0285110, 105.8048170, $at);
        expect(false)->toBeTrue();
    } catch (AttendanceException $e) {
        expect($e->errorCode)->toBe(AttendanceException::BRANCH_NOT_ASSIGNED);
    }
});

test('branch chưa có GPS bị reject', function () {
    $fx = attendanceFixture();
    $fx['branch']->update(['latitude' => null, 'longitude' => null]);
    $at = Carbon::parse('2026-07-30 14:30:00');
    $token = QrAttendanceToken::makeForBranch($fx['branch']->id, $at);
    $service = app(AttendanceService::class);

    try {
        $service->checkIn($fx['user'], $fx['branch']->id, $token, 21.0285110, 105.8048170, $at);
        expect(false)->toBeTrue();
    } catch (AttendanceException $e) {
        expect($e->errorCode)->toBe(AttendanceException::BRANCH_GEO_NOT_CONFIGURED);
    }
});

test('GPS trong radius check-in thành công và ghi đúng fields', function () {
    $fx = attendanceFixture();
    $at = Carbon::parse('2026-07-30 14:30:00');
    $token = QrAttendanceToken::makeForBranch($fx['branch']->id, $at);
    $lat = 21.0285110;
    $lng = 105.8048170;

    $log = app(AttendanceService::class)->checkIn(
        $fx['user'],
        $fx['branch']->id,
        $token,
        $lat,
        $lng,
        $at
    );

    expect($log->food_branch_id)->toBe($fx['branch']->id)
        ->and($log->check_in_at->equalTo($at))->toBeTrue()
        ->and((float) $log->check_in_latitude)->toBe($lat)
        ->and((float) $log->check_in_longitude)->toBe($lng)
        ->and($log->check_in_method)->toBe('qr')
        ->and($log->check_in_distance_meters)->toBe(0)
        ->and($log->check_out_at)->toBeNull();

    expect(AttendanceLog::query()->count())->toBe(1);
});

test('GPS ngoài radius bị reject', function () {
    $fx = attendanceFixture(['radius' => 50]);
    $at = Carbon::parse('2026-07-30 14:30:00');
    $token = QrAttendanceToken::makeForBranch($fx['branch']->id, $at);
    // ~0.01 deg lat ≈ 1.1km
    $service = app(AttendanceService::class);

    try {
        $service->checkIn($fx['user'], $fx['branch']->id, $token, 21.0385110, 105.8048170, $at);
        expect(false)->toBeTrue();
    } catch (AttendanceException $e) {
        expect($e->errorCode)->toBe(AttendanceException::OUTSIDE_BRANCH_RADIUS);
    }
});

test('branch mismatch trên QR bị reject', function () {
    $fx = attendanceFixture();
    $other = FoodBranch::query()->create([
        'user_id' => $fx['owner']->id,
        'name' => 'CN Other',
        'latitude' => 21.0285110,
        'longitude' => 105.8048170,
        'check_in_radius_meters' => 100,
    ]);
    $at = Carbon::parse('2026-07-30 14:30:00');
    // Token for other branch, nhưng gọi checkIn với branch được assign
    $token = QrAttendanceToken::makeForBranch($other->id, $at);
    $service = app(AttendanceService::class);

    try {
        $service->checkIn($fx['user'], $fx['branch']->id, $token, 21.0285110, 105.8048170, $at);
        expect(false)->toBeTrue();
    } catch (AttendanceException $e) {
        expect($e->errorCode)->toBe(AttendanceException::INVALID_QR);
    }
});

test('duplicate check-in bị reject', function () {
    $fx = attendanceFixture();
    $at = Carbon::parse('2026-07-30 14:30:00');
    $token = QrAttendanceToken::makeForBranch($fx['branch']->id, $at);
    $service = app(AttendanceService::class);

    $service->checkIn($fx['user'], $fx['branch']->id, $token, 21.0285110, 105.8048170, $at);

    $token2 = QrAttendanceToken::makeForBranch($fx['branch']->id, $at->copy()->addSeconds(30));
    try {
        $service->checkIn($fx['user'], $fx['branch']->id, $token2, 21.0285110, 105.8048170, $at->copy()->addSeconds(30));
        expect(false)->toBeTrue();
    } catch (AttendanceException $e) {
        expect($e->errorCode)->toBe(AttendanceException::ALREADY_CHECKED_IN);
    }
});

test('check-out trước check-in bị reject', function () {
    $fx = attendanceFixture();
    $at = Carbon::parse('2026-07-30 14:30:00');
    $token = QrAttendanceToken::makeForBranch($fx['branch']->id, $at);

    try {
        app(AttendanceService::class)->checkOut(
            $fx['user'],
            $fx['branch']->id,
            $token,
            21.0285110,
            105.8048170,
            $at
        );
        expect(false)->toBeTrue();
    } catch (AttendanceException $e) {
        expect($e->errorCode)->toBe(AttendanceException::NOT_CHECKED_IN);
    }
});

test('check-out ghi đúng fields và duplicate check-out bị reject', function () {
    $fx = attendanceFixture();
    $service = app(AttendanceService::class);
    $inAt = Carbon::parse('2026-07-30 14:30:00');
    $outAt = Carbon::parse('2026-07-30 18:00:00');

    $service->checkIn(
        $fx['user'],
        $fx['branch']->id,
        QrAttendanceToken::makeForBranch($fx['branch']->id, $inAt),
        21.0285110,
        105.8048170,
        $inAt
    );

    $log = $service->checkOut(
        $fx['user'],
        $fx['branch']->id,
        QrAttendanceToken::makeForBranch($fx['branch']->id, $outAt),
        21.0285200,
        105.8048200,
        $outAt
    );

    expect($log->check_out_at->equalTo($outAt))->toBeTrue()
        ->and((float) $log->check_out_latitude)->toBe(21.0285200)
        ->and((float) $log->check_out_longitude)->toBe(105.8048200)
        ->and($log->check_out_method)->toBe('qr')
        ->and($log->check_out_distance_meters)->toBeInt()
        ->and($log->food_branch_id)->toBe($fx['branch']->id);

    try {
        $service->checkOut(
            $fx['user'],
            $fx['branch']->id,
            QrAttendanceToken::makeForBranch($fx['branch']->id, $outAt),
            21.0285200,
            105.8048200,
            $outAt
        );
        expect(false)->toBeTrue();
    } catch (AttendanceException $e) {
        expect($e->errorCode)->toBe(AttendanceException::ALREADY_CHECKED_OUT);
    }

    expect(AttendanceLog::query()->count())->toBe(1);
});

test('scanQr tiến state check-in rồi check-out', function () {
    $fx = attendanceFixture();
    $service = app(AttendanceService::class);
    $inAt = Carbon::parse('2026-07-30 09:00:00');
    $outAt = Carbon::parse('2026-07-30 17:00:00');

    $log1 = $service->scanQr(
        $fx['user'],
        $fx['branch']->id,
        QrAttendanceToken::makeForBranch($fx['branch']->id, $inAt),
        21.0285110,
        105.8048170,
        $inAt
    );
    expect($log1->check_in_at)->not->toBeNull()
        ->and($log1->check_out_at)->toBeNull();

    $log2 = $service->scanQr(
        $fx['user'],
        $fx['branch']->id,
        QrAttendanceToken::makeForBranch($fx['branch']->id, $outAt),
        21.0285110,
        105.8048170,
        $outAt
    );
    expect($log2->check_out_at)->not->toBeNull();

    try {
        $service->scanQr(
            $fx['user'],
            $fx['branch']->id,
            QrAttendanceToken::makeForBranch($fx['branch']->id, $outAt),
            21.0285110,
            105.8048170,
            $outAt
        );
        expect(false)->toBeTrue();
    } catch (AttendanceException $e) {
        expect($e->errorCode)->toBe(AttendanceException::ALREADY_CHECKED_OUT);
    }
});

test('employee inactive bị reject', function () {
    $fx = attendanceFixture(['active' => false]);
    $at = Carbon::parse('2026-07-30 14:30:00');

    try {
        app(AttendanceService::class)->checkIn(
            $fx['user'],
            $fx['branch']->id,
            QrAttendanceToken::makeForBranch($fx['branch']->id, $at),
            21.0285110,
            105.8048170,
            $at
        );
        expect(false)->toBeTrue();
    } catch (AttendanceException $e) {
        expect($e->errorCode)->toBe(AttendanceException::EMPLOYEE_INACTIVE);
    }
});
