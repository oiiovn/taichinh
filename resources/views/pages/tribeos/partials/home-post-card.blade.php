@php
    $showGroupLink = $showGroupLink ?? true;
    $groupSlug = $groupSlug ?? $post->group->slug;
    $likeCount = $post->reactions->where('type', \App\Models\TribeosPostReaction::TYPE_LIKE)->count();
    $liked = auth()->check() && $post->reactions->where('user_id', auth()->id())->where('type', \App\Models\TribeosPostReaction::TYPE_LIKE)->isNotEmpty();
    $commentCount = $post->comments->count();
    $totalReactions = $post->reactions->count();
    $reactionUrl = route('tribeos.groups.posts.reaction', [$groupSlug, $post->id]);
    $commentUrl = route('tribeos.groups.posts.comments.store', [$groupSlug, $post->id]);
@endphp
<article id="post-{{ $post->id }}" class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-[#e4e6eb]/80"
    x-data="{
        liked: {{ $liked ? 'true' : 'false' }},
        likeCount: {{ $likeCount }},
        commentOpen: {{ $commentCount > 0 ? 'true' : 'false' }},
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
                        div.className = 'flex gap-2 rounded-xl bg-[#f0f2f5] px-3 py-2';
                        div.innerHTML = '<span class=\"font-semibold text-sm\">' + (data.comment.user_name || '—') + '</span><span class=\"text-sm text-[#1c1e21]\">' + (data.comment.body || '') + '</span>';
                        list.appendChild(div);
                    }
                }
                if (input) input.value = '';
                this.commentOpen = true;
            } catch (err) {}
            if (btn) btn.disabled = false;
        }
    }">
    <div class="p-4 pb-3">
        <div class="flex items-start gap-3">
            @if($post->user->avatar_url ?? null)
                <img src="{{ $post->user->avatar_url }}" alt="" class="h-11 w-11 shrink-0 rounded-full object-cover" />
            @else
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#5b7cfa] to-[#8b5cf6] text-sm font-bold text-white">{{ mb_substr($post->user->name ?? 'U', 0, 1) }}</span>
            @endif
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                    <span class="text-[15px] font-semibold">{{ $post->user->name ?? '—' }}</span>
                    @if($showGroupLink)
                        <span class="text-xs text-[#65676b]">· {{ $post->group->name }}</span>
                    @endif
                </div>
                <p class="text-xs text-[#65676b]">{{ $post->created_at->diffForHumans() }}@if($post->edited_at) · Đã chỉnh sửa @endif · 🌐</p>
            </div>
            <button type="button" class="rounded-full p-2 text-[#65676b] hover:bg-[#f0f2f5]">
                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
            </button>
        </div>
                @php
                    $postBodyHtml = preg_replace_callback(
                        '/#([\p{L}\p{N}_]+)/u',
                        fn ($m) => '<a href="'.e(route('tribeos', ['tag' => $m[1]])).'" class="font-medium text-[#1877f2] hover:underline">#'.e($m[1]).'</a>',
                        e($post->body)
                    );
                @endphp
                <p class="mt-3 whitespace-pre-wrap text-[15px] leading-relaxed text-[#1c1e21]">{!! $postBodyHtml !!}</p>
    </div>
    @if($post->image_url ?? null)
        <div class="border-y border-[#f0f2f5]">
            <img src="{{ $post->image_url }}" alt="" class="max-h-[420px] w-full object-cover" />
        </div>
    @endif
    <div class="flex items-center justify-between px-4 py-2 text-sm text-[#65676b]">
        <div class="flex items-center gap-1">
            <span class="flex -space-x-1">
                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-[#1877f2] text-[10px] text-white">👍</span>
                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-[#f02849] text-[10px] text-white">❤</span>
            </span>
            <span class="ml-1" x-text="likeCount">{{ $likeCount }}</span>
        </div>
        <div class="flex gap-3 text-xs">
            <span x-text="commentCount + ' bình luận'">{{ $commentCount }} bình luận</span>
            @if($totalReactions > $likeCount)
                <span>{{ $totalReactions }} tương tác</span>
            @endif
        </div>
    </div>
    <div class="mx-4 border-t border-[#e4e6eb]"></div>
    <div class="grid grid-cols-4 gap-1 px-2 py-1">
        <button type="button" @click="toggleLike()" class="flex items-center justify-center gap-2 rounded-lg py-2.5 text-sm font-semibold hover:bg-[#f0f2f5]" :class="liked ? 'text-[#1877f2]' : 'text-[#65676b]'">
            <svg class="h-5 w-5" :fill="liked ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M7 10v12M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88Z"/></svg>
            Thích
        </button>
        <button type="button" @click="commentOpen = !commentOpen" class="flex items-center justify-center gap-2 rounded-lg py-2.5 text-sm font-semibold text-[#65676b] hover:bg-[#f0f2f5]">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            Bình luận
        </button>
        <button type="button" class="flex items-center justify-center gap-2 rounded-lg py-2.5 text-sm font-semibold text-[#65676b] hover:bg-[#f0f2f5]">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="m22 2-7 20-4-9-9-4z"/></svg>
            Chia sẻ
        </button>
        <button type="button" class="flex items-center justify-center gap-2 rounded-lg py-2.5 text-sm font-semibold text-[#65676b] hover:bg-[#f0f2f5]">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="m19 21-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
            Lưu
        </button>
    </div>
    <div x-show="commentOpen" x-cloak class="border-t border-[#e4e6eb] bg-[#f7f8fa] px-4 py-3">
        <div class="space-y-2" x-ref="commentList">
            @foreach($post->comments as $c)
                <div class="flex gap-2 rounded-xl bg-[#f0f2f5] px-3 py-2">
                    <span class="text-sm font-semibold">{{ $c->user->name ?? '—' }}</span>
                    <span class="text-sm">{{ $c->body }}</span>
                </div>
            @endforeach
        </div>
        <form @submit.prevent="submitComment($event)" class="mt-3 flex gap-2">
            @csrf
            <input type="text" name="body" placeholder="Viết bình luận..." maxlength="2000" class="flex-1 rounded-full border-0 bg-[#f0f2f5] px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1877f2]/30">
            <button type="submit" class="rounded-full bg-[#1877f2] px-4 py-2 text-sm font-semibold text-white hover:bg-[#166fe5]">Gửi</button>
        </form>
    </div>
</article>
