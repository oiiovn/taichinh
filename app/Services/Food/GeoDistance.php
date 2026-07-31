<?php

namespace App\Services\Food;

use App\Exceptions\Food\AttendanceException;
use App\Models\FoodBranch;

/**
 * Khoảng cách GPS (Haversine) tính bằng mét.
 */
class GeoDistance
{
    public const EARTH_RADIUS_METERS = 6_371_000;

    public static function assertValidCoordinates(float $latitude, float $longitude): void
    {
        if ($latitude < -90.0 || $latitude > 90.0 || $longitude < -180.0 || $longitude > 180.0) {
            throw AttendanceException::make(
                AttendanceException::GPS_INVALID,
                'Tọa độ GPS không hợp lệ.',
                422
            );
        }
    }

    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        self::assertValidCoordinates($lat1, $lng1);
        self::assertValidCoordinates($lat2, $lng2);

        $latFrom = deg2rad($lat1);
        $latTo = deg2rad($lat2);
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lngDelta / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_METERS * $c;
    }

    /**
     * @return array{distance_meters: int, radius_meters: int}
     *
     * @throws AttendanceException
     */
    public static function assertWithinBranchRadius(
        FoodBranch $branch,
        float $latitude,
        float $longitude,
    ): array {
        self::assertValidCoordinates($latitude, $longitude);

        if ($branch->latitude === null || $branch->longitude === null) {
            throw AttendanceException::make(
                AttendanceException::BRANCH_GEO_NOT_CONFIGURED,
                'Chi nhánh chưa cấu hình vị trí GPS.',
                422
            );
        }

        $branchLat = (float) $branch->latitude;
        $branchLng = (float) $branch->longitude;
        self::assertValidCoordinates($branchLat, $branchLng);

        $distance = self::distanceMeters($latitude, $longitude, $branchLat, $branchLng);
        $distanceMeters = (int) round($distance);
        $radius = (int) ($branch->check_in_radius_meters ?: 100);

        if ($distanceMeters > $radius) {
            throw AttendanceException::make(
                AttendanceException::OUTSIDE_BRANCH_RADIUS,
                'Bạn đang ở ngoài phạm vi cho phép của chi nhánh ('.$distanceMeters.'m / '.$radius.'m).',
                422
            );
        }

        return [
            'distance_meters' => $distanceMeters,
            'radius_meters' => $radius,
        ];
    }
}
