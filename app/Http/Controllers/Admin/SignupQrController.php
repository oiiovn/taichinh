<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SignupPreset;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
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

        $qrSvg = Builder::create()
            ->writer(new SvgWriter())
            ->data($signupUrl)
            ->size(280)
            ->margin(8)
            ->build()
            ->getString();

        return view('pages.admin.signup-qr', [
            'title' => 'Mã QR đăng ký',
            'presetKey' => self::PRESET_KEY,
            'presetLabel' => SignupPreset::label(self::PRESET_KEY),
            'signupUrl' => $signupUrl,
            'qrSvg' => $qrSvg,
        ]);
    }
}
