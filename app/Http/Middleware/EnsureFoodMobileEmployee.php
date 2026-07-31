<?php

namespace App\Http\Middleware;

use App\Exceptions\Food\AttendanceException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mobile Food staff: auth user phải có Employee active + can_use_food_employee.
 */
class EnsureFoodMobileEmployee
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'error' => ['code' => 'UNAUTHENTICATED'],
            ], 401);
        }

        if (! $user->canUseFoodEmployee()) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chưa được cấp quyền dùng phần nhân viên.',
                'error' => ['code' => 'FORBIDDEN_FOOD_EMPLOYEE'],
            ], 403);
        }

        $employee = $user->employee;
        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không phải nhân viên.',
                'error' => ['code' => AttendanceException::EMPLOYEE_NOT_FOUND],
            ], 403);
        }

        if (! $employee->active) {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản nhân viên đã ngưng hoạt động.',
                'error' => ['code' => AttendanceException::EMPLOYEE_INACTIVE],
            ], 403);
        }

        return $next($request);
    }
}
