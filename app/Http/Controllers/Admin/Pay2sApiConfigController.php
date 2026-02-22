<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pay2sApiConfig;
use Illuminate\Http\Request;

class Pay2sApiConfigController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'secret_key' => ['nullable', 'string', 'max:500'],
            'base_url' => ['nullable', 'string', 'max:500'],
            'path_transactions' => ['nullable', 'string', 'max:255'],
            'bank_accounts' => ['nullable', 'string', 'max:1000'],
            'fetch_begin' => ['nullable', 'string', 'max:20'],
            'fetch_end' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        $config = Pay2sApiConfig::first();
        if (! $config) {
            $config = new Pay2sApiConfig();
        }
        $config->fill($validated);
        $config->save();

        return back()->with('success', 'Đã lưu cấu hình Pay2s.');
    }
}
