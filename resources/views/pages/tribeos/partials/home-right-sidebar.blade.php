@php
    $tribeosGroups = $tribeosGroups ?? collect();
    $connectionSuggestions = $connectionSuggestions ?? collect();
    $trendingTags = $trendingTags ?? [];
    $recentActivityItems = $recentActivityItems ?? collect();
    $currentTag = $currentTag ?? request('tag');
@endphp
<aside class="hidden w-[320px] shrink-0 xl:block">
    <div class="sticky top-[76px] space-y-4">
        @if($connectionSuggestions->isNotEmpty())
            <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-[#e4e6eb]/80">
                <div class="flex items-center justify-between">
                    <h3 class="text-[17px] font-semibold text-[#1c1e21]">Đề xuất kết nối</h3>
                    <a href="{{ route('tribeos.groups.index') }}" class="text-sm font-medium text-[#1877f2] hover:underline">Nhóm</a>
                </div>
                <ul class="mt-3 space-y-3">
                    @foreach($connectionSuggestions->take(4) as $suggestion)
                        @php $person = $suggestion->user; @endphp
                        <li class="flex items-center gap-3">
                            @if($person->avatar_url ?? null)
                                <img src="{{ $person->avatar_url }}" alt="" class="h-11 w-11 rounded-full object-cover" loading="lazy" />
                            @else
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#5b7cfa] to-[#8b5cf6] text-sm font-bold text-white">{{ mb_substr($person->name ?? 'U', 0, 1) }}</span>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[15px] font-semibold">{{ $person->name }}</p>
                                <p class="text-xs text-[#65676b]">{{ $suggestion->mutual_groups }} nhóm chung</p>
                            </div>
                            @if($suggestion->invite_group)
                                <a href="{{ route('tribeos.groups.invite', $suggestion->invite_group->slug) }}" class="shrink-0 rounded-lg bg-[#e7f0ff] px-3 py-1.5 text-sm font-semibold text-[#1877f2] hover:bg-[#dbeafe]">Mời nhóm</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if(count($trendingTags) > 0)
            <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-[#e4e6eb]/80">
                <h3 class="text-[17px] font-semibold text-[#1c1e21]">Xu hướng</h3>
                <ul class="mt-3 space-y-3">
                    @foreach($trendingTags as $t)
                        <li>
                            <a href="{{ route('tribeos', ['tag' => ltrim($t['tag'], '#')]) }}" class="block hover:opacity-80 {{ $currentTag && '#'.$currentTag === $t['tag'] ? 'opacity-100 ring-2 ring-[#1877f2]/20 rounded-lg p-1 -m-1' : '' }}">
                                <p class="text-[15px] font-semibold text-[#1877f2]">{{ $t['tag'] }}</p>
                                <p class="text-xs text-[#65676b]">{{ number_format($t['count'], 0, ',', '.') }} bài viết</p>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if($recentActivityItems->isNotEmpty())
            <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-[#e4e6eb]/80">
                <h3 class="text-[17px] font-semibold text-[#1c1e21]">Hoạt động gần đây</h3>
                <ul class="mt-3 space-y-3">
                    @foreach($recentActivityItems as $ev)
                        <li class="flex gap-3">
                            <span class="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-purple-600 text-center text-[10px] font-bold leading-tight text-white">
                                <span>{{ $ev->occurred_at?->format('d') }}</span>
                                <span class="text-[9px] font-medium opacity-90">T{{ $ev->occurred_at?->format('n') }}</span>
                            </span>
                            <div class="min-w-0">
                                <p class="text-[15px] font-semibold leading-snug line-clamp-2">{{ $ev->title }}</p>
                                <p class="text-xs text-[#65676b]">{{ $ev->subtitle }} · {{ $ev->occurred_at?->diffForHumans() }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="rounded-2xl bg-gradient-to-br from-[#f8faff] to-[#eef2ff] p-4 shadow-sm ring-1 ring-[#e0e7ff]">
            <div class="flex items-start gap-3">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-[#5b7cfa] to-[#8b5cf6] text-white shadow">
                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="8" width="14" height="12" rx="4"/><circle cx="9.5" cy="13" r="1.2" fill="currentColor"/><circle cx="14.5" cy="13" r="1.2" fill="currentColor"/><path d="M12 4v3"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-bold text-[#1c1e21]">Trợ lý AI</p>
                    <p class="mt-0.5 text-sm text-[#65676b]">Gợi ý từ bài viết trong nhóm của bạn</p>
                </div>
            </div>
            @if(($feedPosts ?? collect())->isNotEmpty())
                <ul class="mt-3 space-y-2 text-sm text-[#1877f2]">
                    @foreach(($feedPosts ?? collect())->take(3) as $p)
                        <li class="line-clamp-2">
                            <span class="text-[#65676b]">{{ $p->user->name ?? '—' }}:</span>
                            {{ \Illuminate\Support\Str::limit($p->body, 80) }}
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-3 text-sm text-[#65676b]">Tham gia nhóm và đăng bài để nhận gợi ý nội dung.</p>
            @endif
            <a href="{{ route('tribeos.groups.index') }}" class="mt-3 block w-full rounded-xl bg-gradient-to-r from-[#5b7cfa] to-[#8b5cf6] py-2.5 text-center text-sm font-semibold text-white shadow-sm hover:opacity-95">Đến nhóm của tôi</a>
        </section>
    </div>
</aside>
