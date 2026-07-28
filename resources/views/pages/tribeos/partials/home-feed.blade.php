@php
    $currentTag = $currentTag ?? request('tag');
    $currentSearch = $currentSearch ?? request('q');
@endphp
@if($currentTag)
    <div class="flex items-center justify-between rounded-2xl bg-white px-4 py-3 text-sm shadow-sm ring-1 ring-[#e4e6eb]/80">
        <span>Đang lọc: <strong class="text-[#1877f2]">#{{ $currentTag }}</strong></span>
        <a href="{{ route('tribeos') }}" class="font-semibold text-[#1877f2] hover:underline">Bỏ lọc</a>
    </div>
@endif
@if($currentSearch)
    <div class="flex items-center justify-between rounded-2xl bg-white px-4 py-3 text-sm shadow-sm ring-1 ring-[#e4e6eb]/80">
        <span>Kết quả tìm: <strong>{{ $currentSearch }}</strong></span>
        <a href="{{ route('tribeos', array_filter(['tag' => $currentTag])) }}" class="font-semibold text-[#1877f2] hover:underline">Xóa tìm kiếm</a>
    </div>
@endif

@if(($tribeosGroups ?? collect())->isNotEmpty())
    <div class="flex flex-wrap items-center gap-2 rounded-2xl bg-white px-3 py-2 shadow-sm ring-1 ring-[#e4e6eb]/80">
        <a href="{{ route('tribeos', array_filter(['tag' => $currentTag, 'q' => $currentSearch])) }}" data-filter="all" class="tribeos-filter-tab rounded-lg px-3 py-1.5 text-sm font-medium {{ ($currentFilter ?? 'all') === 'all' ? 'bg-[#e7f0ff] text-[#1877f2]' : 'text-[#65676b] hover:bg-[#f0f2f5]' }}">Tất cả</a>
        <a href="{{ route('tribeos', array_filter(['filter' => 'mine', 'tag' => $currentTag, 'q' => $currentSearch])) }}" data-filter="mine" class="tribeos-filter-tab rounded-lg px-3 py-1.5 text-sm font-medium {{ ($currentFilter ?? '') === 'mine' ? 'bg-[#e7f0ff] text-[#1877f2]' : 'text-[#65676b] hover:bg-[#f0f2f5]' }}">Bài của tôi</a>
        @foreach($tribeosGroups as $g)
            <a href="{{ route('tribeos', array_filter(['filter' => 'group_'.$g->id, 'tag' => $currentTag, 'q' => $currentSearch])) }}" data-filter="group_{{ $g->id }}" class="tribeos-filter-tab rounded-lg px-3 py-1.5 text-sm font-medium {{ ($currentFilter ?? '') === 'group_'.$g->id ? 'bg-[#e7f0ff] text-[#1877f2]' : 'text-[#65676b] hover:bg-[#f0f2f5]' }}">{{ $g->name }}</a>
        @endforeach
    </div>
@endif

<div class="space-y-4 tribeos-feed-list">
    @forelse($feedPosts ?? [] as $post)
        @include('pages.tribeos.partials.home-post-card', ['post' => $post, 'showGroupLink' => true])
    @empty
        <div class="rounded-2xl border border-dashed border-[#ccd0d5] bg-white py-14 text-center shadow-sm">
            <p class="text-[15px] text-[#65676b]">Chưa có bài viết{{ $currentTag ? ' với hashtag này' : '' }}{{ $currentSearch ? ' khớp tìm kiếm' : '' }}.</p>
            <div class="mt-4 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('tribeos.groups.index') }}" class="rounded-xl bg-[#1877f2] px-4 py-2 text-sm font-semibold text-white hover:bg-[#166fe5]">Nhóm của tôi</a>
                <a href="{{ route('tribeos.groups.create') }}" class="rounded-xl bg-[#e7f0ff] px-4 py-2 text-sm font-semibold text-[#1877f2] hover:bg-[#dbeafe]">Tạo nhóm</a>
            </div>
        </div>
    @endforelse
</div>
