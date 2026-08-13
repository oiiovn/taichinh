<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\SignupPreset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showSignin()
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof User && ! $user->is_admin && $user->canManageFoodThongKeBuff()) {
                return redirect()->route('food.thong-ke-buff');
            }
            if ($user instanceof User && $user->isFoodThongKeBuffOnlyUser()) {
                return redirect()->route('food.thong-ke-buff');
            }
            if ($user instanceof User && $user->isFoodBuffOrderOnlyUser()) {
                return redirect()->route('food.dat-don');
            }

            return redirect()->route('dashboard');
        }

        return view('pages.auth.signin', [
            'title' => 'Đăng nhập',
            'hideFullscreenFooter' => true,
        ]);
    }

    public function signupPreset(string $preset)
    {
        if (! SignupPreset::exists($preset)) {
            abort(404);
        }
        session(['signup_preset_key' => $preset]);

        return redirect()->route('signup');
    }

    public function showSignup()
    {
        return view('pages.auth.signup', ['title' => 'Đăng ký']);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required'],
        ], [
            'email.required' => 'Vui lòng nhập email hoặc số điện thoại.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ]);

        $remember = $request->boolean('remember');
        $identifier = trim($validated['email']);
        $user = User::query()->where('email', $identifier)->first();
        if (! $user) {
            $phone = preg_replace('/\s+/', '', $identifier);
            $user = User::query()->where('phone', $identifier)->orWhere('phone', $phone)->first();
        }

        if ($user && Auth::attempt(['email' => $user->email, 'password' => $validated['password']], $remember)) {
            $request->session()->regenerate();
            $intended = $request->session()->get('url.intended');
            if (is_string($intended) && (str_contains($intended, '/login') || str_contains($intended, '/signin'))) {
                $request->session()->forget('url.intended');
            }

            $user = Auth::user();
            $home = route('dashboard');
            if ($user instanceof User && ! $user->is_admin && $user->canManageFoodThongKeBuff()) {
                $home = route('food.thong-ke-buff');
            } elseif ($user instanceof User && $user->isFoodThongKeBuffOnlyUser()) {
                $home = route('food.thong-ke-buff');
            } elseif ($user instanceof User && $user->isFoodBuffOrderOnlyUser()) {
                $home = route('food.dat-don');
            }

            return redirect()->intended($home);
        }

        return back()->withErrors([
            'email' => 'Email, số điện thoại hoặc mật khẩu không đúng.',
        ])->onlyInput('email');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'name.required' => 'Vui lòng nhập họ tên.',
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email này đã được sử dụng.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
        ]);

        $presetKey = $request->session()->pull('signup_preset_key');

        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'password_plain' => mb_strtolower($validated['password'], 'UTF-8'),
        ];

        if ($presetKey !== null && SignupPreset::exists($presetKey)) {
            $userData = array_merge(SignupPreset::userAttributes($presetKey), $userData);
        } else {
            $userData['allowed_features'] = ['tai_chinh'];
        }

        $user = User::create($userData);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(SignupPreset::redirectAfterRegister($presetKey));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('signin');
    }
}
