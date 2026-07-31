<?php

use App\Exceptions\Food\AttendanceException;
use App\Services\Food\GeoDistance;
use App\Services\Food\QrAttendanceToken;
use Carbon\Carbon;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
});

test('QR legacy current minute valid', function () {
    $at = Carbon::parse('2026-07-30 14:30:10');
    $token = QrAttendanceToken::makeLegacy($at);
    expect(QrAttendanceToken::validateLegacy($token, $at))->toBeTrue();
});

test('QR legacy previous minute valid', function () {
    $at = Carbon::parse('2026-07-30 14:30:10');
    $token = QrAttendanceToken::makeLegacy($at->copy()->subMinute());
    expect(QrAttendanceToken::validateLegacy($token, $at))->toBeTrue();
});

test('QR legacy invalid rejected', function () {
    $at = Carbon::parse('2026-07-30 14:30:10');
    expect(QrAttendanceToken::validateLegacy('not-a-valid-token', $at))->toBeFalse();
});

test('QR branch-aware current and previous minute valid', function () {
    $at = Carbon::parse('2026-07-30 14:30:10');
    $branchId = 7;

    QrAttendanceToken::assertValidForBranch(
        QrAttendanceToken::makeForBranch($branchId, $at),
        $branchId,
        $at
    );

    QrAttendanceToken::assertValidForBranch(
        QrAttendanceToken::makeForBranch($branchId, $at->copy()->subMinute()),
        $branchId,
        $at
    );

    expect(true)->toBeTrue();
});

test('QR branch-aware wrong branch rejected as INVALID_QR', function () {
    $at = Carbon::parse('2026-07-30 14:30:10');
    $token = QrAttendanceToken::makeForBranch(1, $at);

    expect(fn () => QrAttendanceToken::assertValidForBranch($token, 2, $at))
        ->toThrow(AttendanceException::class);

    try {
        QrAttendanceToken::assertValidForBranch($token, 2, $at);
    } catch (AttendanceException $e) {
        expect($e->errorCode)->toBe(AttendanceException::INVALID_QR);
    }
});

test('QR branch-aware expired (few minutes ago) rejected as EXPIRED_QR', function () {
    $at = Carbon::parse('2026-07-30 14:30:10');
    $token = QrAttendanceToken::makeForBranch(5, $at->copy()->subMinutes(3));

    try {
        QrAttendanceToken::assertValidForBranch($token, 5, $at);
        expect(false)->toBeTrue();
    } catch (AttendanceException $e) {
        expect($e->errorCode)->toBe(AttendanceException::EXPIRED_QR);
    }
});

test('Haversine same point is ~0m', function () {
    $d = GeoDistance::distanceMeters(21.0285, 105.8542, 21.0285, 105.8542);
    expect($d)->toBeLessThan(1);
});

test('GPS invalid coordinates rejected', function () {
    expect(fn () => GeoDistance::assertValidCoordinates(91, 0))
        ->toThrow(AttendanceException::class);
    expect(fn () => GeoDistance::assertValidCoordinates(0, 181))
        ->toThrow(AttendanceException::class);
});

test('GPS known short distance is approximate', function () {
    // ~111m north at equator-ish: 0.001 deg lat ≈ 111m
    $d = GeoDistance::distanceMeters(21.0, 105.0, 21.001, 105.0);
    expect($d)->toBeGreaterThan(100)->and($d)->toBeLessThan(120);
});
