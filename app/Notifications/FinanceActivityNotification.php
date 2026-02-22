<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Broadcasting\PrivateChannel;

class FinanceActivityNotification extends Notification
{
    /**
     * @param  int  $notifiableUserId  ID người nhận (để broadcast đúng kênh)
     * @param  User|null  $actor  Nếu có: hiển thị avatar + tên người này (vd. người thanh toán); không thì dùng notifiable
     * @param  float|null  $amount  Số tiền (hiển thị trong thông báo)
     */
    public function __construct(
        public int $notifiableUserId,
        public string $action,
        public string $subjectType,
        public string $subjectTitle,
        public string $link,
        public ?User $actor = null,
        public ?float $amount = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        $displayUser = $this->actor ?? $notifiable;
        return [
            'user_name' => $displayUser->name,
            'user_avatar' => $displayUser->avatar_url ?? null,
            'action' => $this->action,
            'subject' => $this->subjectTitle,
            'subject_type' => $this->subjectType,
            'link' => $this->link,
            'amount' => $this->amount,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->notifiableUserId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'finance.activity';
    }
}
