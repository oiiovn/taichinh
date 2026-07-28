@extends('layouts.tribeos-home')

@section('tribeosHomeMain')
@php
    $user = auth()->user();
    $avatarUrl = $user?->avatar_url;
    $initial = mb_strtoupper(mb_substr($user?->name ?? 'U', 0, 1));
    $storyUsers = $storyUsers ?? collect();
@endphp
<div class="space-y-4" id="tribeos-index">
    {{-- Tạo bài viết --}}
    <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-[#e4e6eb]/80">
        <div class="flex gap-3">
            @auth
                @if($avatarUrl)
                    <img src="{{ $avatarUrl }}" alt="" class="h-11 w-11 shrink-0 rounded-full object-cover" />
                @else
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#5b7cfa] to-[#8b5cf6] text-sm font-bold text-white">{{ $initial }}</span>
                @endif
            @else
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#e4e6eb] text-sm font-bold">?</span>
            @endauth
            @if(auth()->check() && ($tribeosGroups ?? collect())->isNotEmpty())
                <div class="min-w-0 flex-1" x-data="{ open: false, selectedSlug: @json($tribeosGroups->first()->slug) }" @keydown.escape.window="open=false">
                    <button type="button" @click="open=true" class="w-full rounded-full bg-[#f0f2f5] px-4 py-3 text-left text-[15px] text-[#65676b] hover:bg-[#e4e6eb] transition-colors">
                        Bạn đang nghĩ gì?
                    </button>
                    <div x-show="open" x-cloak x-transition class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4" @click.self="open=false">
                        <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-2xl" @click.stop>
                            <h3 class="mb-4 text-lg font-bold">Tạo bài viết</h3>
                            <form x-ref="createPostForm" @submit.prevent="
                                var form = $refs.createPostForm;
                                var slug = selectedSlug;
                                var bodyEl = form.querySelector('textarea[name=body]');
                                var body = bodyEl && bodyEl.value ? bodyEl.value.trim() : '';
                                if (!body) return;
                                var btn = form.querySelector('button[type=submit]');
                                if (btn) btn.disabled = true;
                                var fd = new FormData(form);
                                fd.set('body', body);
                                fetch('/tribeos/groups/' + slug + '/posts', {
                                    method: 'POST',
                                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                    body: fd
                                }).then(function(r) { return r.json(); }).then(function(data) {
                                    if (data.postHtml) {
                                        var wrap = document.querySelector('.tribeos-feed-list');
                                        if (wrap) {
                                            var demo = wrap.querySelector('[data-demo-feed]');
                                            if (demo) demo.remove();
                                            wrap.insertAdjacentHTML('afterbegin', data.postHtml);
                                        }
                                    }
                                    open = false;
                                    if (bodyEl) bodyEl.value = '';
                                }).catch(function() {}).finally(function() { if (btn) btn.disabled = false; });
                            ">
                                @csrf
                                <select name="group_select" x-model="selectedSlug" class="mb-3 w-full rounded-xl border border-[#e4e6eb] bg-[#f0f2f5] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1877f2]/30">
                                    @foreach($tribeosGroups ?? [] as $g)
                                        <option value="{{ $g->slug }}">{{ $g->name }}</option>
                                    @endforeach
                                </select>
                                <textarea name="body" rows="4" placeholder="Bạn đang nghĩ gì?" required maxlength="10000" class="w-full resize-none rounded-xl border border-[#e4e6eb] bg-[#f0f2f5] px-3 py-2 text-[15px] focus:outline-none focus:ring-2 focus:ring-[#1877f2]/30"></textarea>
                                <div class="mt-4 flex justify-end gap-2">
                                    <button type="button" @click="open=false" class="rounded-xl px-4 py-2 text-sm font-semibold text-[#65676b] hover:bg-[#f0f2f5]">Hủy</button>
                                    <button type="submit" class="rounded-xl bg-[#1877f2] px-4 py-2 text-sm font-semibold text-white hover:bg-[#166fe5]">Đăng</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @else
                <a href="{{ auth()->check() ? route('tribeos.groups.index') : route('login') }}" class="flex flex-1 items-center rounded-full bg-[#f0f2f5] px-4 py-3 text-[15px] text-[#65676b] hover:bg-[#e4e6eb]">
                    Bạn đang nghĩ gì?
                </a>
            @endif
        </div>
        <div class="mt-3 border-t border-[#e4e6eb] pt-3">
            <div class="grid grid-cols-2 gap-1 sm:grid-cols-4">
                @foreach([
                    ['label' => 'Ảnh / Video', 'color' => 'text-emerald-600', 'icon' => 'M4 16l4.586-4.586a2 2 0 0 1 2.828 0L16 16m-2-2l1.586-1.586a2 2 0 0 1 2.828 0L20 14M6 20h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2z'],
                    ['label' => 'Cảm xúc', 'color' => 'text-amber-500', 'icon' => 'M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z M8 14s1.5 2 4 2 4-2 4-2 M9 9h.01M15 9h.01'],
                    ['label' => 'AI Gợi ý', 'color' => 'text-violet-600', 'icon' => 'm12 3 1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5L12 3z'],
                    ['label' => 'Thăm dò', 'color' => 'text-sky-600', 'icon' => 'M9 19v-6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v6M9 19h6M9 19H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-4'],
                ] as $composerAction)
                    <button type="button" class="flex items-center justify-center gap-2 rounded-xl py-2.5 text-sm font-semibold text-[#65676b] hover:bg-[#f0f2f5] {{ $composerAction['color'] }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $composerAction['icon'] }}"/></svg>
                        <span class="hidden sm:inline">{{ $composerAction['label'] }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    </section>

    @if($storyUsers->isNotEmpty())
    <section class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-[#e4e6eb]/80">
        <div class="tribeos-home-scroll flex gap-2 overflow-x-auto pb-1">
            <a href="{{ ($tribeosGroups ?? collect())->isNotEmpty() ? route('tribeos.groups.show', $tribeosGroups->first()->slug) : route('tribeos.groups.index') }}" class="relative block h-[200px] w-[112px] shrink-0 overflow-hidden rounded-2xl bg-[#f0f2f5] shadow-sm">
                <div class="absolute inset-0 bg-gradient-to-b from-[#1877f2]/20 to-[#1877f2]/5"></div>
                @if($avatarUrl)
                    <img src="{{ $avatarUrl }}" alt="" class="absolute left-3 top-3 h-9 w-9 rounded-full border-2 border-[#1877f2] object-cover" />
                @endif
                <span class="absolute bottom-10 left-1/2 flex h-9 w-9 -translate-x-1/2 items-center justify-center rounded-full border-4 border-white bg-[#1877f2] text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                </span>
                <span class="absolute bottom-3 left-0 right-0 text-center text-xs font-semibold text-[#1c1e21]">Đăng bài</span>
            </a>
            @foreach($storyUsers as $story)
                @php $storyUser = $story->user; @endphp
                <a href="{{ route('tribeos', ['filter' => 'user_'.$storyUser->id]) }}" class="relative block h-[200px] w-[112px] shrink-0 overflow-hidden rounded-2xl shadow-sm ring-1 ring-[#e4e6eb]/60">
                    @if($storyUser->avatar_url ?? null)
                        <img src="{{ $storyUser->avatar_url }}" alt="" class="h-full w-full object-cover" loading="lazy" />
                    @else
                        <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-[#5b7cfa] to-[#8b5cf6] text-4xl font-bold text-white">{{ mb_substr($storyUser->name ?? 'U', 0, 1) }}</div>
                    @endif
                    <div class="absolute inset-0 bg-gradient-to-b from-black/30 via-transparent to-black/50"></div>
                    @if($storyUser->avatar_url ?? null)
                        <img src="{{ $storyUser->avatar_url }}" alt="" class="absolute left-3 top-3 h-9 w-9 rounded-full border-2 border-[#1877f2] object-cover" />
                    @else
                        <span class="absolute left-3 top-3 flex h-9 w-9 items-center justify-center rounded-full border-2 border-[#1877f2] bg-white text-xs font-bold">{{ mb_substr($storyUser->name ?? 'U', 0, 1) }}</span>
                    @endif
                    <span class="absolute bottom-3 left-2 right-2 truncate text-center text-xs font-semibold text-white drop-shadow">{{ $storyUser->name }}</span>
                </a>
            @endforeach
        </div>
    </section>
    @endif

  <div id="tribeos-feed-wrap" class="space-y-4">
        @include('pages.tribeos.partials.home-feed')
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    var wrap = document.getElementById('tribeos-feed-wrap');
    if (!wrap) return;
    wrap.addEventListener('click', function(e) {
        var t = e.target.closest('a.tribeos-filter-tab');
        if (!t || !t.href) return;
        e.preventDefault();
        var url = new URL(t.href);
        var qs = url.searchParams;
        qs.set('partial', 'feed');
        if (!qs.get('filter')) qs.set('filter', 'all');
        fetch(url.pathname + '?' + qs.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
        }).then(function(r) { return r.text(); }).then(function(html) {
            wrap.innerHTML = html;
        });
    });
})();
</script>
@endpush
