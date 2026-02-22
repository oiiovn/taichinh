<div class="space-y-6">
    @include('pages.tai-chinh.partials.no-khoan-vay.header')
    @if(($unifiedLoans ?? collect())->isNotEmpty())
        @include('pages.tai-chinh.partials.no-khoan-vay.tier2-stats')
    @endif
    @include('pages.tai-chinh.partials.no-khoan-vay.danh-sach')
</div>
