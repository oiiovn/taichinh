<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('id')->paginate(15);
        return view('pages.admin.users.index', [
            'title' => 'Quản lý user',
            'users' => $users,
        ]);
    }

    public function create()
    {
        return view('pages.admin.users.create', [
            'title' => 'Thêm user',
            'featureList' => config('features.list', []),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'is_admin' => ['boolean'],
        ]);
        $validated['password'] = Hash::make($validated['password']);
        $validated['is_admin'] = $request->boolean('is_admin');
        $features = array_values(array_keys($request->input('features', [])));
        $validated['allowed_features'] = $features !== [] ? $features : ['tai_chinh'];
        User::create($validated);
        return redirect()->route('admin.users.index')->with('success', 'Đã thêm user.');
    }

    public function edit(User $user)
    {
        return view('pages.admin.users.edit', [
            'title' => 'Sửa user',
            'user' => $user,
            'featureList' => config('features.list', []),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'is_admin' => ['boolean'],
        ];
        if ($request->filled('password')) {
            $rules['password'] = ['confirmed', Password::defaults()];
        }
        $validated = $request->validate($rules);
        if ($request->filled('password')) {
            $validated['password'] = Hash::make($request->password);
        }
        $validated['is_admin'] = $request->boolean('is_admin');
        $validated['allowed_features'] = array_values(array_keys($request->input('features', [])));
        $user->update($validated);
        return redirect()->route('admin.users.index')->with('success', 'Đã cập nhật user.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Không thể xóa chính mình.');
        }
        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'Đã xóa user.');
    }
}
