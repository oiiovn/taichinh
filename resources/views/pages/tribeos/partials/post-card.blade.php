@php
    $showGroupLink = $showGroupLink ?? true;
    $groupSlug = $groupSlug ?? $post->group->slug;
    $likeCount = $post->reactions->where('type', \App\Models\TribeosPostReaction::TYPE_LIKE)->count();
    $liked = $post->reactions->where('user_id', auth()->id())->where('type', \App\Models\TribeosPostReaction::TYPE_LIKE)->isNotEmpty();
    $commentCount = $post->comments->count();
    $reactionUrl = route('tribeos.groups.posts.reaction', [$groupSlug, $post->id]);
    $commentUrl = route('tribeos.groups.posts.comments.store', [$groupSlug, $post->id]);
@endphp
<article id="post-{{ $post->id }}" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-700 dark:bg-gray-800/50"
    x-data="{
        liked: {{ $liked ? 'true' : 'false' }},
        likeCount: {{ $likeCount }},
        commentOpen: false,
        commentCount: {{ $commentCount }},
        commentUrl: '{{ $commentUrl }}',
        reactionUrl: '{{ $reactionUrl }}',
        csrf: '{{ csrf_token() }}',
        async toggleLike() {
            try {
                const res = await fetch(this.reactionUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ _token: this.csrf, type: 'like' })
                });
                const data = await res.json();
                if (data.liked !== undefined) this.liked = data.liked;
                if (data.count !== undefined) this.likeCount = data.count;
            } catch (err) {}
        },
        async submitComment(e) {
            const form = e.target;
            const input = form.querySelector('input[name=body]');
            const body = (input && input.value) ? input.value.trim() : '';
            if (!body) return;
            const btn = form.querySelector('button[type=submit]');
            if (btn) btn.disabled = true;
            try {
                const res = await fetch(this.commentUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ _token: this.csrf, body: body })
                });
                const data = await res.json();
                if (data.comment_count !== undefined) this.commentCount = data.comment_count;
                if (data.comment) {
                    const list = form.previousElementSibling;
                    if (list) {
                        const div = document.createElement('div');
                        div.className = 'flex gap-2 py-1.5';
                        const s1 = document.createElement('span');
                        s1.className = 'font-medium text-theme-sm text-gray-800 dark:text-white';
                        s1.textContent = data.comment.user_name || '—';
                        const s2 = document.createElement('span');
                        s2.className = 'text-theme-sm text-gray-600 dark:text-gray-300';
                        s2.textContent = data.comment.body || '';
                        const s3 = document.createElement('span');
                        s3.className = 'text-theme-xs text-gray-400';
                        s3.textContent = data.comment.created_at_human || '';
                        div.append(s1, s2, s3);
                        list.appendChild(div);
                    }
                }
                if (input) input.value = '';
            } catch (err) {}
            if (btn) btn.disabled = false;
        }
    }">
    @if($post->image_url ?? null)
        <div class="aspect-video w-full bg-gray-100 dark:bg-gray-700">
            <img src="{{ $post->image_url }}" alt="" class="h-full w-full object-cover" />
        </div>
    @endif
    <div class="p-4">
        <div class="flex items-start gap-3">
            @if($post->user->avatar_url ?? null)
                <img src="{{ $post->user->avatar_url }}" alt="" class="h-10 w-10 shrink-0 rounded-full object-cover" />
            @else
                <div class="h-10 w-10 shrink-0 rounded-full bg-gray-300 dark:bg-gray-600"></div>
            @endif
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-medium text-gray-900 dark:text-white">{{ $post->user->name ?? '—' }}</span>
                    <span class="text-theme-xs text-gray-500 dark:text-gray-400">{{ $post->created_at->diffForHumans() }}@if($post->edited_at) · đã sửa @endif</span>
                    @if($showGroupLink)
                        <span class="text-theme-xs text-gray-500 dark:text-gray-400">·</span>
                        <a href="{{ route('tribeos.groups.show', $post->group->slug) }}" class="text-theme-sm font-medium text-brand-600 hover:underline dark:text-brand-400">{{ $post->group->name }}</a>
                    @endif
                </div>
                <p class="mt-2 whitespace-pre-wrap text-theme-sm text-gray-700 dark:text-gray-300">{{ $post->body }}</p>
            </div>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-4 border-t border-gray-100 pt-3 dark:border-gray-700">
            <button type="button" @click="toggleLike()" class="inline-flex items-center gap-1.5 text-theme-sm hover:opacity-80 focus:outline-none" :class="liked ? 'text-red-500 dark:text-red-400' : 'text-gray-500 dark:text-gray-400'">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" :fill="liked ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                <span x-text="likeCount">0</span>
            </button>
            <button type="button" @click="commentOpen = !commentOpen" class="inline-flex items-center gap-1.5 text-theme-sm text-gray-600 dark:text-gray-400 hover:opacity-80 focus:outline-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <span x-text="commentCount">0</span>
            </button>
        </div>
        <div x-show="commentOpen" x-transition class="mt-3 border-t border-gray-100 dark:border-gray-700 pt-3">
            <div class="divide-y divide-gray-50 dark:divide-gray-700/50" x-ref="commentList">
                @foreach($post->comments as $c)
                    <div class="flex gap-2 py-1.5">
                        <span class="font-medium text-theme-sm text-gray-800 dark:text-white">{{ $c->user->name ?? '—' }}</span>
                        <span class="text-theme-sm text-gray-600 dark:text-gray-300">{{ $c->body }}</span>
                        <span class="text-theme-xs text-gray-400">{{ $c->created_at->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
            <form @submit.prevent="submitComment($event)" class="mt-2 flex gap-2">
                @csrf
                <input type="text" name="body" placeholder="Viết bình luận..." maxlength="2000" class="flex-1 rounded-lg border border-gray-300 px-3 py-1.5 text-theme-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <button type="submit" class="rounded-lg bg-brand-500 px-3 py-1.5 text-theme-sm font-medium text-white hover:bg-brand-600">Gửi</button>
            </form>
        </div>
    </div>
</article>
