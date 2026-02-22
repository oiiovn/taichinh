@php
    $currentFilter = $currentFilter ?? request('filter', 'all');
@endphp
@if(($tribeosGroups ?? collect())->isNotEmpty())
    <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 dark:border-white/10 pb-3">
        <a href="{{ route('tribeos', ['filter' => 'all']) }}" data-filter="all" class="tribeos-filter-tab rounded-lg px-4 py-2 text-sm font-medium {{ $currentFilter === 'all' ? 'bg-gray-200 dark:bg-white/10 text-[#1877F2]' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5' }}">Tất cả</a>
        <a href="{{ route('tribeos', ['filter' => 'mine']) }}" data-filter="mine" class="tribeos-filter-tab rounded-lg px-4 py-2 text-sm font-medium {{ $currentFilter === 'mine' ? 'bg-gray-200 dark:bg-white/10 text-[#1877F2]' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5' }}">Bài của tôi</a>
        @foreach($tribeosGroups ?? [] as $g)
            <a href="{{ route('tribeos', ['filter' => 'group_'.$g->id]) }}" data-filter="group_{{ $g->id }}" class="tribeos-filter-tab rounded-lg px-4 py-2 text-sm font-medium {{ $currentFilter === 'group_'.$g->id ? 'bg-gray-200 dark:bg-white/10 text-[#1877F2]' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5' }}">{{ $g->name }}</a>
        @endforeach
    </div>
@endif
<div class="space-y-4 tribeos-feed-list mt-4">
    @forelse($feedPosts ?? [] as $post)
        @include('pages.tribeos.partials.post-card', ['post' => $post, 'showGroupLink' => true])
    @empty
        <div class="rounded-lg border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 py-12 text-center">
            <p class="text-[15px] text-gray-500 dark:text-gray-400">Chưa có bài đăng. Vào nhóm để đăng bài hoặc xem nội dung!</p>
            <a href="{{ route('tribeos.groups.index') }}" class="mt-3 inline-block rounded-lg bg-[#1877F2] px-4 py-2 text-sm font-medium text-white hover:bg-[#166fe5]">Nhóm của tôi</a>
        </div>
    @endforelse
</div>
