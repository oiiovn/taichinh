<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SignupPreset;
use Illuminate\Support\Facades\URL;

class SignupQrController extends Controller
{
    private const PRESET_KEY = 'food_thong_ke_buff';

    public function index()
    {
        if (! SignupPreset::exists(self::PRESET_KEY)) {
            abort(404);
        }

        $signupUrl = URL::signedRoute('signup.preset', ['preset' => self::PRESET_KEY]);

        return view('pages.admin.signup-qr', [
            'title' => 'Mã QR đăng ký',
            'presetKey' => self::PRESET_KEY,
            'presetLabel' => SignupPreset::label(self::PRESET_KEY),
            'signupUrl' => $signupUrl,
        ]);
    }
}
