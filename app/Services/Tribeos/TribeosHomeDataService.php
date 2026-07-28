<?php

namespace App\Services\Tribeos;

use App\Models\TribeosActivity;
use App\Models\TribeosGroupInvitation;
use App\Models\TribeosGroupMember;
use App\Models\TribeosPost;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TribeosHomeDataService
{
    /**
     * @return array{
     *     tribeosGroups: Collection,
     *     feedPosts: Collection,
     *     storyUsers: Collection,
     *     connectionSuggestions: Collection,
     *     trendingTags: array<int, array{tag: string, count: int}>,
     *     recentActivityItems: Collection,
     *     pendingInvitationCount: int
     * }
     */
    public function forUser(User $user, ?string $filter = 'all', ?string $hashtag = null, ?string $search = null): array
    {
        $tribeosGroups = $user->tribeosGroups()->orderByPivot('created_at', 'desc')->get();
        $groupIds = $tribeosGroups->pluck('id');

        $feedPosts = $this->feedPosts($user, $groupIds, $filter, $hashtag, $search);
        $storyUsers = $this->storyUsers($user, $groupIds);
        $connectionSuggestions = $this->connectionSuggestions($user, $groupIds, $tribeosGroups);
        $trendingTags = $this->trendingTags($groupIds);
        $recentActivityItems = $this->recentActivities($groupIds)->map(function (TribeosActivity $activity) {
            return (object) [
                'title' => $this->activityLabel($activity),
                'subtitle' => $activity->group->name ?? '',
                'occurred_at' => $activity->created_at,
            ];
        });
        $pendingInvitationCount = $user->tribeosInvitationsReceived()
            ->where('status', TribeosGroupInvitation::STATUS_PENDING)
            ->count();

        return compact(
            'tribeosGroups',
            'feedPosts',
            'storyUsers',
            'connectionSuggestions',
            'trendingTags',
            'recentActivityItems',
            'pendingInvitationCount',
        );
    }

    public function feedPosts(User $user, Collection $groupIds, string $filter = 'all', ?string $hashtag = null, ?string $search = null): Collection
    {
        if ($groupIds->isEmpty()) {
            return collect();
        }

        $query = TribeosPost::query()
            ->whereIn('tribeos_group_id', $groupIds)
            ->with(['group', 'user', 'reactions', 'comments.user'])
            ->orderByDesc('created_at');

        if ($filter === 'mine') {
            $query->where('user_id', $user->id);
        } elseif (preg_match('/^group_(\d+)$/', $filter, $m)) {
            $gid = (int) $m[1];
            if ($groupIds->contains($gid)) {
                $query->where('tribeos_group_id', $gid);
            }
        } elseif (preg_match('/^user_(\d+)$/', $filter, $m)) {
            $uid = (int) $m[1];
            $inNetwork = TribeosGroupMember::query()
                ->where('user_id', $uid)
                ->whereIn('tribeos_group_id', $groupIds)
                ->exists();
            if ($inNetwork) {
                $query->where('user_id', $uid);
            }
        }

        if ($hashtag !== null && $hashtag !== '') {
            $tag = ltrim($hashtag, '#');
            if ($tag !== '') {
                $query->where('body', 'like', '%#'.$tag.'%');
            }
        }

        if ($search !== null && trim($search) !== '') {
            $query->where('body', 'like', '%'.trim($search).'%');
        }

        return $query->limit(50)->get();
    }

    public function storyUsers(User $user, Collection $groupIds): Collection
    {
        if ($groupIds->isEmpty()) {
            return collect();
        }

        $since = now()->subDays(30);

        $latestByUser = TribeosPost::query()
            ->selectRaw('user_id, MAX(created_at) as last_post_at')
            ->whereIn('tribeos_group_id', $groupIds)
            ->where('created_at', '>=', $since)
            ->where('user_id', '!=', $user->id)
            ->groupBy('user_id')
            ->orderByDesc('last_post_at')
            ->limit(10)
            ->get();

        if ($latestByUser->isEmpty()) {
            return collect();
        }

        $userIds = $latestByUser->pluck('user_id');
        $users = User::query()->whereIn('id', $userIds)->get()->keyBy('id');

        return $latestByUser->map(function ($row) use ($users) {
            $u = $users->get($row->user_id);
            if (! $u) {
                return null;
            }

            return (object) [
                'user' => $u,
                'last_post_at' => $row->last_post_at,
            ];
        })->filter()->values();
    }

    public function connectionSuggestions(User $user, Collection $groupIds, Collection $tribeosGroups): Collection
    {
        if ($groupIds->isEmpty()) {
            return collect();
        }

        $rows = TribeosGroupMember::query()
            ->selectRaw('user_id, COUNT(DISTINCT tribeos_group_id) as shared_groups')
            ->whereIn('tribeos_group_id', $groupIds)
            ->where('user_id', '!=', $user->id)
            ->groupBy('user_id')
            ->orderByDesc('shared_groups')
            ->limit(8)
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $userIds = $rows->pluck('user_id');
        $users = User::query()->whereIn('id', $userIds)->get()->keyBy('id');

        $adminGroupIds = TribeosGroupMember::query()
            ->where('user_id', $user->id)
            ->whereIn('tribeos_group_id', $groupIds)
            ->whereIn('role', [TribeosGroupMember::ROLE_OWNER, TribeosGroupMember::ROLE_ADMIN])
            ->pluck('tribeos_group_id');

        return $rows->map(function ($row) use ($users, $groupIds, $adminGroupIds, $tribeosGroups) {
            $u = $users->get($row->user_id);
            if (! $u) {
                return null;
            }

            $sharedMemberGroupIds = TribeosGroupMember::query()
                ->where('user_id', $u->id)
                ->whereIn('tribeos_group_id', $groupIds)
                ->pluck('tribeos_group_id');

            $inviteGroup = $tribeosGroups->first(function ($g) use ($sharedMemberGroupIds, $adminGroupIds) {
                return $sharedMemberGroupIds->contains($g->id) && $adminGroupIds->contains($g->id);
            }) ?? $tribeosGroups->first(function ($g) use ($sharedMemberGroupIds) {
                return $sharedMemberGroupIds->contains($g->id);
            });

            return (object) [
                'user' => $u,
                'mutual_groups' => (int) $row->shared_groups,
                'invite_group' => $inviteGroup,
            ];
        })->filter()->values();
    }

    /**
     * @return array<int, array{tag: string, count: int}>
     */
    public function trendingTags(Collection $groupIds, int $limit = 5): array
    {
        if ($groupIds->isEmpty()) {
            return [];
        }

        $bodies = TribeosPost::query()
            ->whereIn('tribeos_group_id', $groupIds)
            ->where('created_at', '>=', now()->subDays(90))
            ->pluck('body');

        $counts = [];
        foreach ($bodies as $body) {
            if (! is_string($body) || $body === '') {
                continue;
            }
            if (preg_match_all('/#([\p{L}\p{N}_]+)/u', $body, $matches)) {
                foreach ($matches[1] as $raw) {
                    $key = '#'.Str::lower($raw);
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                }
            }
        }

        arsort($counts);
        $top = array_slice($counts, 0, $limit, true);

        $result = [];
        foreach ($top as $tag => $count) {
            $result[] = ['tag' => $tag, 'count' => $count];
        }

        return $result;
    }

    public function recentActivities(Collection $groupIds): Collection
    {
        if ($groupIds->isEmpty()) {
            return collect();
        }

        return TribeosActivity::query()
            ->whereIn('tribeos_group_id', $groupIds)
            ->with(['group', 'user'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
    }

    public function activityLabel(TribeosActivity $activity): string
    {
        $meta = $activity->metadata ?? [];

        return match ($activity->type) {
            TribeosActivity::TYPE_EVENT_CREATED => (string) ($meta['title'] ?? 'Sự kiện mới trong nhóm'),
            TribeosActivity::TYPE_MEMBER_ADDED => 'Thành viên mới: '.($activity->user->name ?? '—'),
            TribeosActivity::TYPE_POST_CREATED => 'Bài viết mới: '.(string) ($meta['post_body_preview'] ?? '—'),
            TribeosActivity::TYPE_WALLET_ADDED => 'Cập nhật ví trong nhóm',
            default => 'Hoạt động trong nhóm',
        };
    }
}
