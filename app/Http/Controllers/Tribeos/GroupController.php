<?php

namespace App\Http\Controllers\Tribeos;

use App\Http\Controllers\Controller;
use App\Models\TribeosGroup;
use App\Models\TribeosGroupInvitation;
use App\Models\TribeosGroupMember;
use App\Models\TribeosActivity;
use App\Models\TribeosPost;
use App\Models\TribeosPostComment;
use App\Models\TribeosPostReaction;
use App\Models\User;
use App\Notifications\TribeosGroupInvitationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('tribeos')->with('error', 'Vui lòng đăng nhập.');
        }

        $groups = $user->tribeosGroups()
            ->with(['owner', 'members.user'])
            ->orderByPivot('created_at', 'desc')
            ->get();

        return view('pages.tribeos.groups.index', [
            'title' => 'Nhóm của tôi',
            'groups' => $groups,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (!$request->user()) {
            return redirect()->route('tribeos.groups.index')->with('error', 'Vui lòng đăng nhập.');
        }
        return view('pages.tribeos.groups.create', ['title' => 'Tạo nhóm']);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('tribeos.groups.index')->with('error', 'Vui lòng đăng nhập.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
        ]);
        if ($validator->fails()) {
            return redirect()->route('tribeos.groups.create')
                ->withErrors($validator)
                ->withInput();
        }

        $slug = Str::slug($request->input('name'));
        $base = $slug;
        $i = 0;
        while (TribeosGroup::where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }

        $group = TribeosGroup::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'slug' => $slug,
            'owner_user_id' => $user->id,
        ]);

        TribeosGroupMember::create([
            'tribeos_group_id' => $group->id,
            'user_id' => $user->id,
            'role' => TribeosGroupMember::ROLE_OWNER,
        ]);

        return redirect()->route('tribeos.groups.show', $group->slug)
            ->with('success', 'Đã tạo nhóm.');
    }

    public function show(Request $request, string $slug): View|RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('tribeos.groups.index')->with('error', 'Vui lòng đăng nhập.');
        }

        $group = TribeosGroup::where('slug', $slug)->with(['owner', 'members.user', 'posts' => fn ($q) => $q->with(['user', 'reactions.user', 'comments.user'])->orderByDesc('created_at')])->firstOrFail();
        if (!$group->hasMember($user)) {
            return redirect()->route('tribeos.groups.index')->with('error', 'Bạn không thuộc nhóm này.');
        }

        return view('pages.tribeos.groups.show', [
            'title' => $group->name,
            'group' => $group,
            'canManage' => $group->isOwner($user) || $group->isAdmin($user),
        ]);
    }

    public function storePost(Request $request, string $slug): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $request->wantsJson() ? response()->json(['error' => 'Vui lòng đăng nhập.'], 403) : redirect()->route('tribeos.groups.index')->with('error', 'Vui lòng đăng nhập.');
        }

        $group = TribeosGroup::where('slug', $slug)->firstOrFail();
        if (!$group->hasMember($user)) {
            return $request->wantsJson() ? response()->json(['error' => 'Bạn không thuộc nhóm này.'], 403) : redirect()->route('tribeos.groups.index')->with('error', 'Bạn không thuộc nhóm này.');
        }

        $validator = Validator::make($request->all(), [
            'body' => 'required|string|max:10000',
        ]);
        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $validator->errors()->first('body', 'Nội dung không hợp lệ.')], 422);
            }
            return redirect()->route('tribeos.groups.show', $group->slug)
                ->withErrors($validator)
                ->withInput();
        }

        $post = TribeosPost::create([
            'tribeos_group_id' => $group->id,
            'user_id' => $user->id,
            'body' => $request->input('body'),
        ]);

        TribeosActivity::log($group->id, $user->id, TribeosActivity::TYPE_POST_CREATED, 'tribeos_post', $post->id, ['post_body_preview' => \Str::limit($post->body, 80)]);

        if ($request->wantsJson()) {
            $post->load(['user', 'group', 'reactions', 'comments']);
            $postHtml = view('pages.tribeos.partials.post-card', ['post' => $post, 'showGroupLink' => true])->render();
            return response()->json(['success' => true, 'postHtml' => $postHtml]);
        }

        return redirect()->route('tribeos.groups.show', $group->slug)->with('success', 'Đã đăng bài.');
    }

    public function storeComment(Request $request, string $slug, int $post): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $request->wantsJson() ? response()->json(['error' => 'Vui lòng đăng nhập.'], 403) : redirect()->route('tribeos.groups.index')->with('error', 'Vui lòng đăng nhập.');
        }

        $group = TribeosGroup::where('slug', $slug)->firstOrFail();
        if (!$group->hasMember($user)) {
            return $request->wantsJson() ? response()->json(['error' => 'Bạn không thuộc nhóm này.'], 403) : redirect()->route('tribeos.groups.index')->with('error', 'Bạn không thuộc nhóm này.');
        }

        $postModel = TribeosPost::where('tribeos_group_id', $group->id)->findOrFail($post);

        $validator = Validator::make($request->all(), [
            'body' => 'required|string|max:2000',
        ]);
        if ($validator->fails()) {
            return $request->wantsJson() ? response()->json(['errors' => $validator->errors()], 422) : redirect()->route('tribeos.groups.show', $group->slug)->withErrors($validator)->withInput();
        }

        $comment = $postModel->comments()->create([
            'user_id' => $user->id,
            'body' => $request->input('body'),
        ]);
        $comment->load('user');

        if ($request->wantsJson()) {
            return response()->json([
                'comment' => [
                    'id' => $comment->id,
                    'body' => $comment->body,
                    'user_name' => $comment->user->name ?? '—',
                    'created_at_human' => $comment->created_at->diffForHumans(),
                ],
                'comment_count' => $postModel->comments()->count(),
            ]);
        }

        return redirect()->route('tribeos.groups.show', $group->slug)->with('success', 'Đã thêm bình luận.');
    }

    public function toggleReaction(Request $request, string $slug, int $post): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $request->wantsJson() ? response()->json(['error' => 'Vui lòng đăng nhập.'], 403) : redirect()->route('tribeos.groups.index')->with('error', 'Vui lòng đăng nhập.');
        }

        $group = TribeosGroup::where('slug', $slug)->firstOrFail();
        if (!$group->hasMember($user)) {
            return $request->wantsJson() ? response()->json(['error' => 'Bạn không thuộc nhóm này.'], 403) : redirect()->route('tribeos.groups.index')->with('error', 'Bạn không thuộc nhóm này.');
        }

        $postModel = TribeosPost::where('tribeos_group_id', $group->id)->findOrFail($post);

        $type = $request->input('type', TribeosPostReaction::TYPE_LIKE);
        if ($type !== TribeosPostReaction::TYPE_LIKE) {
            return $request->wantsJson() ? response()->json(['error' => 'Invalid type.'], 422) : redirect()->route('tribeos.groups.show', $group->slug);
        }

        $reaction = TribeosPostReaction::where('tribeos_post_id', $postModel->id)->where('user_id', $user->id)->first();
        if ($reaction) {
            if ($reaction->type === $type) {
                $reaction->delete();
                $liked = false;
            } else {
                $reaction->update(['type' => $type]);
                $liked = true;
            }
        } else {
            TribeosPostReaction::create([
                'tribeos_post_id' => $postModel->id,
                'user_id' => $user->id,
                'type' => $type,
            ]);
            $liked = true;
        }

        $count = $postModel->reactions()->where('type', TribeosPostReaction::TYPE_LIKE)->count();

        if ($request->wantsJson()) {
            return response()->json(['liked' => $liked, 'count' => $count]);
        }
        return redirect()->route('tribeos.groups.show', $group->slug);
    }

    public function invite(Request $request, string $slug): View|RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('tribeos.groups.index')->with('error', 'Vui lòng đăng nhập.');
        }

        $group = TribeosGroup::where('slug', $slug)->firstOrFail();
        if (!$group->isAdmin($user)) {
            return redirect()->route('tribeos.groups.show', $group->slug)->with('error', 'Bạn không có quyền mời thành viên.');
        }

        return view('pages.tribeos.groups.invite', [
            'title' => 'Mời thành viên — ' . $group->name,
            'group' => $group,
        ]);
    }

    public function searchUsers(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([], 403);
        }

        $group = TribeosGroup::where('slug', $slug)->firstOrFail();
        if (!$group->isAdmin($user)) {
            return response()->json([], 403);
        }

        $q = trim((string) $request->input('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $memberIds = $group->members()->pluck('user_id')->toArray();
        $pendingInviteeIds = $group->invitations()
            ->where('status', TribeosGroupInvitation::STATUS_PENDING)
            ->pluck('invitee_user_id')
            ->toArray();
        $excludeIds = array_unique(array_merge($memberIds, $pendingInviteeIds));

        $users = User::where('id', '!=', $user->id)
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', '%' . $q . '%')
                    ->orWhere('email', 'like', '%' . $q . '%');
            })
            ->when(count($excludeIds) > 0, fn ($query) => $query->whereNotIn('id', $excludeIds))
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'email'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email]);

        return response()->json($users);
    }

    public function storeInvite(Request $request, string $slug): RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('tribeos.groups.index')->with('error', 'Vui lòng đăng nhập.');
        }

        $group = TribeosGroup::where('slug', $slug)->firstOrFail();
        if (!$group->isAdmin($user)) {
            return redirect()->route('tribeos.groups.show', $group->slug)->with('error', 'Bạn không có quyền mời thành viên.');
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'role' => 'in:member,admin',
        ]);
        if ($validator->fails()) {
            return redirect()->route('tribeos.groups.invite', $group->slug)
                ->withErrors($validator)
                ->withInput();
        }

        $invitee = User::findOrFail($request->input('user_id'));

        if ($group->hasMember($invitee)) {
            return redirect()->route('tribeos.groups.invite', $group->slug)
                ->with('error', 'Người này đã là thành viên nhóm.')
                ->withInput();
        }

        if ($group->hasPendingInvite($invitee)) {
            return redirect()->route('tribeos.groups.invite', $group->slug)
                ->with('error', 'Đã gửi lời mời cho người này, đang chờ họ xác nhận.')
                ->withInput();
        }

        $invitation = TribeosGroupInvitation::create([
            'tribeos_group_id' => $group->id,
            'inviter_user_id' => $user->id,
            'invitee_user_id' => $invitee->id,
            'role' => $request->input('role', 'member'),
            'status' => TribeosGroupInvitation::STATUS_PENDING,
        ]);

        $invitee->notify(new TribeosGroupInvitationNotification($invitation));

        return redirect()->route('tribeos.groups.show', $group->slug)
            ->with('success', 'Đã gửi lời mời. Người được mời sẽ nhận thông báo và có thể xác nhận hoặc từ chối.');
    }

    public function updateMemberRole(Request $request, string $slug, int $member): RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('tribeos.groups.index')->with('error', 'Vui lòng đăng nhập.');
        }

        $group = TribeosGroup::where('slug', $slug)->firstOrFail();
        if (!$group->isOwner($user)) {
            return redirect()->route('tribeos.groups.show', $group->slug)->with('error', 'Chỉ chủ nhóm mới đổi được vai trò.');
        }

        $memberModel = TribeosGroupMember::where('tribeos_group_id', $group->id)->where('id', $member)->firstOrFail();
        if ($memberModel->isOwner()) {
            return redirect()->route('tribeos.groups.show', $group->slug)->with('error', 'Không thể đổi vai trò chủ nhóm.');
        }

        $role = $request->input('role');
        if (!in_array($role, [TribeosGroupMember::ROLE_ADMIN, TribeosGroupMember::ROLE_MEMBER], true)) {
            return redirect()->route('tribeos.groups.show', $group->slug)->with('error', 'Vai trò không hợp lệ.');
        }

        $memberModel->update(['role' => $role]);

        return redirect()->route('tribeos.groups.show', $group->slug)->with('success', 'Đã cập nhật vai trò.');
    }

    public function leave(Request $request, string $slug): RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('tribeos.groups.index')->with('error', 'Vui lòng đăng nhập.');
        }

        $group = TribeosGroup::where('slug', $slug)->firstOrFail();
        $membership = TribeosGroupMember::where('tribeos_group_id', $group->id)->where('user_id', $user->id)->first();
        if (!$membership) {
            return redirect()->route('tribeos.groups.index')->with('error', 'Bạn không thuộc nhóm này.');
        }
        if ($membership->isOwner()) {
            return redirect()->route('tribeos.groups.show', $group->slug)->with('error', 'Chủ nhóm không thể rời nhóm. Hãy chuyển quyền chủ nhóm hoặc giải tán nhóm.');
        }

        $membership->delete();

        return redirect()->route('tribeos.groups.index')->with('success', 'Đã rời nhóm.');
    }
}
