@extends('layouts.tribeos')

@section('tribeosContent')
    <div class="p-4 sm:p-6 space-y-4" id="tribeos-index">
        {{-- Ô tạo bài --}}
        <div class="flex gap-3 pb-4 border-b border-gray-200 dark:border-white/10">
            @auth
                @if(auth()->user()->avatar_url ?? null)
                    <img src="{{ auth()->user()->avatar_url }}" alt="" class="h-10 w-10 shrink-0 rounded-full object-cover" />
                @else
                    <div class="h-10 w-10 shrink-0 rounded-full bg-[#1877F2]/30 flex items-center justify-center text-[#1877F2] font-semibold text-sm">{{ mb_substr(auth()->user()->name ?? 'U', 0, 1) }}</div>
                @endif
                @if(($tribeosGroups ?? collect())->isNotEmpty())
                    <div class="flex-1 min-w-0" x-data="{ open: false, selectedSlug: @json($tribeosGroups->first()->slug) }" @keydown.escape.window="open=false; var m=document.getElementById('tribeos-post-modal'); if(m) m.style.display='none'"
                        x-init="var m=document.getElementById('tribeos-post-modal'); if(m) m.style.display='none'">
                        <button type="button" @click="open=true; var m=document.getElementById('tribeos-post-modal'); if(m) m.style.display='flex'" class="w-full rounded-full bg-gray-100 dark:bg-gray-700/80 px-4 py-3 text-left text-[15px] text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600/80 transition-colors">
                            Bạn đang nghĩ gì?
                        </button>
                        <div id="tribeos-post-modal" x-show="open" x-cloak x-transition class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50" style="display: none;" @click.self="open=false; var m=document.getElementById('tribeos-post-modal'); if(m) m.style.display='none'">
                            <div class="w-full max-w-lg rounded-xl bg-white dark:bg-[#242526] shadow-xl p-4" @click.stop>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Tạo bài viết</h3>
                                <form x-ref="createPostForm" @submit.prevent="
                                    var form = $refs.createPostForm;
                                    var slug = selectedSlug || (form.querySelector('select[name=group_select]') && form.querySelector('select[name=group_select]').value);
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
                                                var empty = wrap.querySelector('.py-12');
                                                if (empty) empty.remove();
                                                wrap.insertAdjacentHTML('afterbegin', data.postHtml);
                                            }
                                        }
                                        open = false; var m = document.getElementById('tribeos-post-modal'); if (m) m.style.display = 'none';
                                        if (bodyEl) bodyEl.value = '';
                                    }).catch(function() {}).finally(function() { if (btn) btn.disabled = false; });
                                ">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Đăng trong nhóm</label>
                                        <select name="group_select" x-model="selectedSlug" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-[#3a3b3c] text-gray-900 dark:text-white px-3 py-2 text-sm">
                                            @foreach($tribeosGroups ?? [] as $g)
                                                <option value="{{ $g->slug }}">{{ $g->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <textarea name="body" rows="4" placeholder="Bạn đang nghĩ gì?" required maxlength="10000" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-[#3a3b3c] text-gray-900 dark:text-white px-3 py-2 text-[15px] resize-none"></textarea>
                                    <div class="mt-4 flex justify-end gap-2">
                                        <button type="button" @click="open=false; var m=document.getElementById('tribeos-post-modal'); if(m) m.style.display='none'" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/5">Hủy</button>
                                        <button type="submit" class="rounded-lg bg-[#1877F2] px-4 py-2 text-sm font-medium text-white hover:bg-[#166fe5]">Đăng</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @else
                    <a href="{{ route('tribeos.groups.index') }}" class="flex-1 rounded-full bg-gray-100 dark:bg-gray-700/80 px-4 py-3 text-left text-[15px] text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600/80 transition-colors block">
                        Bạn đang nghĩ gì? (Tham gia nhóm để đăng bài)
                    </a>
                @endif
            @endauth
        </div>

        {{-- Feed: tabs + danh sách (load partial khi đổi filter) --}}
        <div id="tribeos-feed-wrap">
            @include('pages.tribeos.partials.feed-content', ['currentFilter' => request('filter', 'all')])
        </div>
    </div>
    <script>
    (function() {
        document.getElementById('tribeos-feed-wrap') && document.getElementById('tribeos-feed-wrap').addEventListener('click', function(e) {
            var t = e.target.closest('a.tribeos-filter-tab');
            if (!t || !t.href) return;
            e.preventDefault();
            var url = new URL(t.href);
            var filter = url.searchParams.get('filter') || 'all';
            var wrap = document.getElementById('tribeos-feed-wrap');
            if (!wrap) return;
            fetch(url.pathname + '?filter=' + encodeURIComponent(filter) + '&partial=feed', {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
            }).then(function(r) { return r.text(); }).then(function(html) {
                wrap.innerHTML = html;
                if (window.history && window.history.pushState) {
                    window.history.pushState({ filter: filter }, '', url.pathname + '?' + url.searchParams.toString());
                }
            }).catch(function() { window.location.href = t.href; });
        });
    })();
    </script>
@endsection
