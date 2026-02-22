<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentConfig;
use Illuminate\Http\Request;

class PaymentConfigController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'bank_id' => ['nullable', 'string', 'max:20'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'account_holder' => ['nullable', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'qr_template' => ['nullable', 'string', 'in:compact,compact2,qr_only'],
        ]);

        $config = PaymentConfig::first();
        if (!$config) {
            $config = new PaymentConfig();
        }
        $config->fill($validated);
        $config->save();

        return back()->with('success', 'Đã lưu cấu hình thanh toán.');
    }
}
