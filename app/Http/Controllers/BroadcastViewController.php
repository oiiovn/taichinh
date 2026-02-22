<?php

namespace App\Http\Controllers;

use App\Models\Broadcast;
use Illuminate\Http\Request;

class BroadcastViewController extends Controller
{
    /**
     * Danh sách thông báo hệ thống (broadcast) áp dụng cho user.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $broadcasts = Broadcast::forUser($user)->orderByDesc('created_at')->paginate(20);
        $readAtMap = $user->broadcasts()->whereIn('broadcast_id', $broadcasts->pluck('id'))->get()->pluck('pivot.read_at', 'id');
        return view('pages.notifications.index', [
            'title' => 'Thông báo',
            'broadcasts' => $broadcasts,
            'readAtMap' => $readAtMap,
        ]);
    }

    /**
     * Xem chi tiết + đánh dấu đã đọc.
     */
    public function show(Request $request, Broadcast $broadcast)
    {
        $user = $request->user();
        if (! $broadcast->appliesToUser($user)) {
            abort(404);
        }
        $user->broadcasts()->syncWithoutDetaching([
            $broadcast->id => ['read_at' => now()],
        ]);
        return view('pages.notifications.show', [
            'title' => $broadcast->title,
            'broadcast' => $broadcast,
        ]);
    }

    /**
     * API: đánh dấu đã đọc (dùng từ dropdown).
     */
    public function markRead(Request $request, Broadcast $broadcast)
    {
        $user = $request->user();
        if (! $broadcast->appliesToUser($user)) {
            return response()->json(['ok' => false], 403);
        }
        $user->broadcasts()->syncWithoutDetaching([
            $broadcast->id => ['read_at' => now()],
        ]);
        return response()->json(['ok' => true]);
    }
}
