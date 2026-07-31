<?php

namespace App\Exceptions\Food;

use RuntimeException;

/**
 * Domain error cho mobile attendance — map sang HTTP/JSON ở API layer.
 */
class AttendanceException extends RuntimeException
{
    public const EMPLOYEE_NOT_FOUND = 'EMPLOYEE_NOT_FOUND';

    public const EMPLOYEE_INACTIVE = 'EMPLOYEE_INACTIVE';

    public const BRANCH_NOT_ASSIGNED = 'BRANCH_NOT_ASSIGNED';

    public const BRANCH_NOT_FOUND = 'BRANCH_NOT_FOUND';

    public const BRANCH_GEO_NOT_CONFIGURED = 'BRANCH_GEO_NOT_CONFIGURED';

    public const INVALID_QR = 'INVALID_QR';

    public const EXPIRED_QR = 'EXPIRED_QR';

    public const GPS_INVALID = 'GPS_INVALID';

    public const OUTSIDE_BRANCH_RADIUS = 'OUTSIDE_BRANCH_RADIUS';

    public const ALREADY_CHECKED_IN = 'ALREADY_CHECKED_IN';

    public const NOT_CHECKED_IN = 'NOT_CHECKED_IN';

    public const ALREADY_CHECKED_OUT = 'ALREADY_CHECKED_OUT';

    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus = 422,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function make(string $errorCode, string $message, int $httpStatus = 422): self
    {
        return new self($errorCode, $message, $httpStatus);
    }
}
