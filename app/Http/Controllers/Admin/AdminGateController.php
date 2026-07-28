<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureAdminGateUnlocked;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminGateController extends Controller
{
    public function show(Request $request)
    {
        if (EnsureAdminGateUnlocked::isUnlocked($request)) {
            return redirect()->intended(route('admin.index'));
        }

        return view('pages.admin.gate', [
            'title' => 'Mở khóa Admin',
        ]);
    }

    public function unlock(Request $request)
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ], [
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ]);

        if (! $this->passwordMatches($request->user(), $validated['password'])) {
            throw ValidationException::withMessages([
                'password' => 'Mật khẩu không đúng.',
            ]);
        }

        EnsureAdminGateUnlocked::unlock($request);

        $intended = $request->session()->pull('admin_gate_intended', route('admin.index'));

        return redirect()->to($intended);
    }

    public function lock(Request $request)
    {
        EnsureAdminGateUnlocked::lock($request);

        return redirect()->route('dashboard');
    }

    private function passwordMatches($user, string $password): bool
    {
        $gatePassword = (string) config('admin.gate_password');

        if ($gatePassword !== '') {
            return hash_equals($gatePassword, $password);
        }

        return Hash::check($password, $user->password);
    }
}
