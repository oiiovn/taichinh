<?php

namespace App\Notifications;

use App\Models\TribeosGroupInvitation;
use Illuminate\Notifications\Notification;

class TribeosGroupInvitationNotification extends Notification
{
    public function __construct(
        public TribeosGroupInvitation $invitation
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $group = $this->invitation->group;
        $inviter = $this->invitation->inviter;
        return [
            'type' => 'tribeos_invitation',
            'invitation_id' => $this->invitation->id,
            'group_id' => $group->id,
            'group_name' => $group->name,
            'group_slug' => $group->slug,
            'inviter_id' => $inviter->id,
            'inviter_name' => $inviter->name,
            'user_name' => $inviter->name,
            'user_avatar' => $inviter->avatar_url ?? null,
            'subject' => $group->name,
            'subject_type' => 'Lời mời nhóm',
            'action' => ' mời bạn tham gia nhóm ',
            'link' => route('tribeos.invitations.index'),
        ];
    }
}
