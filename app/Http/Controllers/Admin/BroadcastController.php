<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Broadcast;
use App\Models\PlanConfig;
use Illuminate\Http\Request;

class BroadcastController extends Controller
{
    public function index()
    {
        $broadcasts = Broadcast::with('creator')->orderByDesc('created_at')->paginate(15);
        return view('pages.admin.broadcasts.index', [
            'title' => 'Thông báo / Broadcast',
            'broadcasts' => $broadcasts,
        ]);
    }

    public function create()
    {
        return view('pages.admin.broadcasts.create', [
            'title' => 'Tạo thông báo',
            'plansList' => PlanConfig::getList(),
            'featureList' => config('features.list', []),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:10000'],
            'type' => ['required', 'string', 'in:maintenance,feature,info,urgent'],
            'target_type' => ['required', 'string', 'in:all,plan,feature'],
            'target_value' => ['nullable', 'string', 'max:64'],
        ]);
        $validated['created_by'] = auth()->id();
        if (($validated['target_type'] ?? '') === 'all') {
            $validated['target_value'] = null;
        }
        Broadcast::create($validated);
        return redirect()->route('admin.broadcasts.index')->with('success', 'Đã gửi thông báo.');
    }
}
