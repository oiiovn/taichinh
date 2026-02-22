<?php

namespace App\View\Components\header;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class NotificationDropdown extends Component
{
    public function __construct()
    {
        //
    }

    public function render(): View|Closure|string
    {
        $user = auth()->user();
        $notifications = [];
        $unreadCount = 0;
        $defaultAvatar = '/images/user/user-02.jpg';

        if ($user) {
            $unreadCount = $user->unreadNotifications()->count();
            $raw = $user->unreadNotifications()->orderByDesc('created_at')->take(20)->get();
            $notifications = $raw->map(function ($n) use ($user, $defaultAvatar) {
                $data = $n->data ?? [];
                $amount = isset($data['amount']) ? (float) $data['amount'] : null;
                return [
                    'id' => $n->id,
                    'userName' => $data['user_name'] ?? $user->name,
                    'userImage' => $data['user_avatar'] ?? $user->avatar_url ?? $defaultAvatar,
                    'action' => $data['action'] ?? '',
                    'project' => $data['subject'] ?? '',
                    'type' => $data['subject_type'] ?? 'Giao dịch',
                    'time' => $n->created_at->diffForHumans(),
                    'link' => $data['link'] ?? '#',
                    'amount' => $amount,
                    'amountFormatted' => $amount !== null ? number_format($amount, 0, ',', '.') . ' ₫' : null,
                ];
            })->values()->all();
        }

        return view('components.header.notification-dropdown', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }
}
