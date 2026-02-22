<?php

namespace App\Http\Controllers\Tribeos;

use App\Http\Controllers\Controller;
use App\Models\TribeosActivity;
use App\Models\TribeosGroupInvitation;
use App\Models\TribeosGroupMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('tribeos')->with('error', 'Vui lòng đăng nhập.');
        }

        $invitations = $user->tribeosInvitationsReceived()
            ->where('status', TribeosGroupInvitation::STATUS_PENDING)
            ->with(['group', 'inviter'])
            ->orderByDesc('created_at')
            ->get();

        return view('pages.tribeos.invitations.index', [
            'title' => 'Lời mời tham gia nhóm',
            'invitations' => $invitations,
        ]);
    }

    public function accept(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }

        $invitation = TribeosGroupInvitation::where('id', $id)
            ->where('invitee_user_id', $user->id)
            ->where('status', TribeosGroupInvitation::STATUS_PENDING)
            ->with('group')
            ->firstOrFail();

        $group = $invitation->group;
        if ($group->hasMember($user)) {
            $invitation->update(['status' => TribeosGroupInvitation::STATUS_ACCEPTED]);
            return redirect()->route('tribeos.groups.show', $group->slug)->with('success', 'Bạn đã là thành viên nhóm.');
        }

        TribeosGroupMember::create([
            'tribeos_group_id' => $group->id,
            'user_id' => $user->id,
            'role' => $invitation->role,
        ]);

        $invitation->update(['status' => TribeosGroupInvitation::STATUS_ACCEPTED]);

        TribeosActivity::log($group->id, $user->id, TribeosActivity::TYPE_MEMBER_ADDED, 'user', (int) $user->id, ['inviter_id' => $invitation->inviter_user_id]);

        $this->markInvitationNotificationRead($user, $id);

        return redirect()->route('tribeos.groups.show', $group->slug)->with('success', 'Bạn đã tham gia nhóm.');
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }

        $invitation = TribeosGroupInvitation::where('id', $id)
            ->where('invitee_user_id', $user->id)
            ->where('status', TribeosGroupInvitation::STATUS_PENDING)
            ->firstOrFail();

        $invitation->update(['status' => TribeosGroupInvitation::STATUS_REJECTED]);

        $this->markInvitationNotificationRead($user, $id);

        return redirect()->route('tribeos.invitations.index')->with('success', 'Đã từ chối lời mời.');
    }

    private function markInvitationNotificationRead($user, int $invitationId): void
    {
        $user->unreadNotifications()
            ->where('type', \App\Notifications\TribeosGroupInvitationNotification::class)
            ->get()
            ->each(function ($n) use ($invitationId) {
                $data = $n->data;
                if (isset($data['invitation_id']) && (int) $data['invitation_id'] === $invitationId) {
                    $n->markAsRead();
                }
            });
    }
}
