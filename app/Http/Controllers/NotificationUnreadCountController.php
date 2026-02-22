<?php

namespace App\Http\Controllers;

use App\Models\Broadcast;

class NotificationUnreadCountController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $laravelUnread = $user->unreadNotifications()->count();
        $broadcastUnread = Broadcast::forUser($user)
            ->where(function ($q) use ($user) {
                $q->whereDoesntHave('users', fn ($q2) => $q2->where('user_id', $user->id))
                    ->orWhereHas('users', fn ($q2) => $q2->where('user_id', $user->id)->whereNull('broadcast_user.read_at'));
            })
            ->count();
        return response()->json(['unread_count' => $laravelUnread + $broadcastUnread]);
    }
}
