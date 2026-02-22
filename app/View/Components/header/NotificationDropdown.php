<?php

namespace App\View\Components\header;

use App\Models\Broadcast;
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
        $typeLabels = ['maintenance' => 'Bảo trì', 'feature' => 'Tính năng', 'info' => 'Thông tin', 'urgent' => 'Khẩn'];

        if ($user) {
            $laravelUnread = $user->unreadNotifications()->count();
            $broadcastUnread = Broadcast::forUser($user)
                ->where(function ($q) use ($user) {
                    $q->whereDoesntHave('users', fn ($q2) => $q2->where('user_id', $user->id))
                        ->orWhereHas('users', fn ($q2) => $q2->where('user_id', $user->id)->whereNull('broadcast_user.read_at'));
                })
                ->count();
            $unreadCount = $laravelUnread + $broadcastUnread;

            $laravel = $user->unreadNotifications()->orderByDesc('created_at')->take(20)->get()->map(function ($n) use ($user, $defaultAvatar) {
                $data = $n->data ?? [];
                $amount = isset($data['amount']) ? (float) $data['amount'] : null;
                return [
                    'sort_at' => $n->created_at,
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
            });
            $broadcasts = Broadcast::forUser($user)->orderByDesc('created_at')->take(20)->get()->map(function ($b) use ($typeLabels, $defaultAvatar) {
                return [
                    'sort_at' => $b->created_at,
                    'userName' => 'Hệ thống',
                    'userImage' => $defaultAvatar,
                    'action' => $b->title,
                    'project' => '',
                    'type' => $typeLabels[$b->type] ?? $b->type,
                    'time' => $b->created_at->diffForHumans(),
                    'link' => route('thong-bao.show', $b),
                ];
            });
            $notifications = $laravel->concat($broadcasts)->sortByDesc('sort_at')->take(20)->values()->map(function ($item) {
                unset($item['sort_at']);
                return $item;
            })->all();
        }

        return view('components.header.notification-dropdown', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }
}
