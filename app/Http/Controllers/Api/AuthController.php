<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Food\FoodMeResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * API auth cho app mobile: đăng nhập trả token Sanctum, đăng xuất thu hồi token.
 */
class AuthController extends Controller
{
    /**
     * POST /api/v1/login
     * Body: email, password
     * Trả về: token, token_type, user; thêm food (me-like) khi user có quyền/employee Food.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $request->email)->first();
        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email hoặc mật khẩu không đúng.'],
            ]);
        }

        $token = $user->createToken('mobile-app')->plainTextToken;

        $payload = [
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ];

        $user->load(['employee.foodBranches']);
        if (
            $user->employee
            || $user->canUseFoodEmployee()
            || $user->canUseQrChamCong()
            || $user->canManageFoodChamCong()
            || $user->canManageFoodEmployees()
        ) {
            $payload['food'] = (new FoodMeResource($user))->resolve();
        }

        return response()->json($payload);
    }

    /**
     * POST /api/v1/logout
     * Header: Authorization: Bearer {token}
     * Thu hồi token hiện tại.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Đã đăng xuất.']);
    }
}
