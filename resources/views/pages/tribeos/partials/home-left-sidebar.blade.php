@php
    $tribeosGroups = $tribeosGroups ?? collect();
    $pendingInvitationCount = (int) ($pendingInvitationCount ?? 0);
    $navItems = [
        ['label' => 'Trang chủ', 'active' => request()->routeIs('tribeos'), 'path' => route('tribeos'), 'icon' => 'home'],
        ['label' => 'Nhóm', 'active' => request()->routeIs('tribeos.groups.*'), 'path' => route('tribeos.groups.index'), 'icon' => 'users-group'],
        ['label' => 'Lời mời', 'active' => request()->routeIs('tribeos.invitations.*'), 'path' => route('tribeos.invitations.index'), 'icon' => 'users', 'badge' => $pendingInvitationCount],
    ];
@endphp
<aside class="hidden w-[280px] shrink-0 lg:block">
    <nav class="sticky top-[76px] space-y-1">
        @foreach($navItems as $item)
            <a href="{{ $item['path'] }}"
               class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-[15px] font-medium transition {{ $item['active'] ? 'bg-[#e7f0ff] text-[#1877f2]' : 'text-[#1c1e21] hover:bg-[#f0f2f5]' }}">
                <span class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $item['active'] ? 'bg-white text-[#1877f2]' : 'bg-[#e4e6eb] text-[#050505]' }}">
                    @include('pages.tribeos.partials.home-icon', ['name' => $item['icon']])
                    @if(!empty($item['badge']))
                        <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-[#f02849] px-1 text-[10px] font-bold text-white">{{ $item['badge'] > 9 ? '9+' : $item['badge'] }}</span>
                    @endif
                </span>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="mt-6 border-t border-[#e4e6eb] pt-4">
        <div class="flex items-center justify-between px-3">
            <h3 class="text-[17px] font-semibold text-[#65676b]">Lối tắt</h3>
            <a href="{{ route('tribeos.groups.index') }}" class="text-xs font-medium text-[#1877f2] hover:underline">Tất cả</a>
        </div>
        <ul class="mt-2 space-y-0.5">
            @forelse($tribeosGroups->take(8) as $g)
                <li>
                    <a href="{{ route('tribeos.groups.show', $g->slug) }}" class="flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-[#f0f2f5]">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-[#5b7cfa] to-[#8b5cf6] text-xs font-bold text-white">{{ mb_substr($g->name, 0, 1) }}</span>
                        <span class="truncate text-[15px] font-medium">{{ $g->name }}</span>
                    </a>
                </li>
            @empty
                <li class="px-3 py-2 text-sm text-[#65676b]">Chưa có nhóm. <a href="{{ route('tribeos.groups.create') }}" class="font-medium text-[#1877f2] hover:underline">Tạo nhóm</a></li>
            @endforelse
        </ul>
    </div>

    <div class="relative mt-6 overflow-hidden rounded-2xl bg-gradient-to-br from-[#4f7cff] via-[#6366f1] to-[#8b5cf6] p-5 text-white shadow-lg">
        <p class="text-lg font-bold leading-snug">TribeOS</p>
        <p class="mt-2 max-w-[160px] text-sm text-white/90">Bài viết, nhóm và lời mời — tất cả từ dữ liệu tài khoản của bạn.</p>
        <a href="{{ route('tribeos.groups.create') }}" class="mt-4 inline-block rounded-full bg-white px-5 py-2 text-sm font-semibold text-[#4f46e5] shadow-sm hover:bg-white/95">Tạo nhóm</a>
        <div class="pointer-events-none absolute -bottom-2 -right-2 h-28 w-28 opacity-90">
            <svg viewBox="0 0 120 120" class="h-full w-full" fill="none"><circle cx="60" cy="60" r="50" fill="white" fill-opacity=".15"/><rect x="35" y="40" width="50" height="42" rx="12" fill="white"/><circle cx="50" cy="58" r="5" fill="#4f46e5"/><circle cx="70" cy="58" r="5" fill="#4f46e5"/><path d="M48 72h24" stroke="#4f46e5" stroke-width="3" stroke-linecap="round"/></svg>
        </div>
    </div>
</aside>
